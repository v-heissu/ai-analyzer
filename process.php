<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Aumenta timeout per analisi complesse (fino a 10 minuti)
// Con 5 query × 5 AI models = 25 chiamate seriali → può richiedere 8-10 minuti
set_time_limit(600);
ini_set('max_execution_time', '600');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log della richiesta
file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Request received\n", FILE_APPEND);

require_once 'config.php';

// Includi tutti i file API con path assoluti
require_once __DIR__ . '/apis/gemini.php';
require_once __DIR__ . '/apis/openai.php';
require_once __DIR__ . '/apis/claude.php';
require_once __DIR__ . '/apis/grok.php';
require_once __DIR__ . '/apis/perplexity.php';
require_once __DIR__ . '/apis/dataforseo.php';
require_once __DIR__ . '/AnalysisEngine.php';

class AIAnalyzer {
    private $config;
    private $cache_dir = 'data/';
    
    public function __construct() {
        $this->config = include 'config.php';
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0777, true);
        }
    }
    
    public function process($input) {
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Processing request\n", FILE_APPEND);
        
        // Se è solo richiesta di fanout queries
        if (isset($input['action']) && $input['action'] === 'fanout_only') {
            return $this->generateFanoutOnly($input);
        }
        
        // Altrimenti processa l'analisi completa
        return $this->analyze($input);
    }
    
    private function generateFanoutOnly($input) {
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Generating fanout queries only\n", FILE_APPEND);
        
        $result = [
            'query' => $input['query'],
            'brand' => $input['brand'] ?? '',
            'fanout_queries' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($input['apis']['gemini']['enabled'] && $input['apis']['gemini']['key']) {
            try {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Calling Gemini for fanout generation\n", FILE_APPEND);
                $gemini = new GeminiAPI($input['apis']['gemini']['key'], $input['apis']['gemini']['model']);
                $fanout_queries = $gemini->generateFanOutQueries($input['query'], $input['brand']);
                $result['fanout_queries'] = $fanout_queries;
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Generated " . count($fanout_queries) . " fanout queries\n", FILE_APPEND);
            } catch (Exception $e) {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Fanout generation error: " . $e->getMessage() . "\n", FILE_APPEND);
                $result['error'] = 'Errore nella generazione delle fanout queries: ' . $e->getMessage();
            }
        } else {
            $result['error'] = 'API Gemini non configurata';
        }
        
        return $result;
    }
    
    public function analyze($input) {
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Starting full analysis\n", FILE_APPEND);
        
        $cache_key = md5(json_encode($input));
        $cache_file = $this->cache_dir . $cache_key . '.json';
        
        // Check cache
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $this->config['cache_duration']) {
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Using cached result\n", FILE_APPEND);
            return json_decode(file_get_contents($cache_file), true);
        }
        
        // Gestisci sia singola query che array di queries
        $queries_to_process = [];
        if (isset($input['queries']) && is_array($input['queries'])) {
            // Array di queries selezionate dall'utente
            $queries_to_process = $input['queries'];
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Processing " . count($queries_to_process) . " selected queries\n", FILE_APPEND);
        } elseif (isset($input['query'])) {
            // Singola query (prompt mode o legacy)
            $queries_to_process = [$input['query']];
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Processing single query\n", FILE_APPEND);
        } else {
            throw new Exception('Nessuna query fornita');
        }
        
        $results = [
            'queries' => $queries_to_process,
            'original_query' => $input['original_query'] ?? $queries_to_process[0],
            'brand' => $input['brand'] ?? '',
            'debug_mode' => $input['debug_mode'] ?? false,
            'is_keyword_mode' => $input['is_keyword_mode'] ?? false,
            'timestamp' => date('Y-m-d H:i:s'),
            'ai_responses' => [],
            'serp_data' => [],
            'analysis' => [],
            'metrics' => [],
            'fanout_queries' => []
        ];
        
        // Se abbiamo fanout queries originali dal primo step, includile nei risultati
        if (isset($input['fanout_queries']) && is_array($input['fanout_queries'])) {
            $results['fanout_queries'] = $input['fanout_queries'];
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Including " . count($input['fanout_queries']) . " original fanout queries\n", FILE_APPEND);
        }
        
        try {
            // Step 1: Processa ogni query selezionata con tutte le AI abilitate
            foreach ($queries_to_process as $query_index => $query) {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Processing query $query_index: $query\n", FILE_APPEND);
                $ai_results = $this->processWithAllAIs($query, $input['apis'], $input['brand']);
                $results['ai_responses'][] = $ai_results;
            }
            
            // Step 2: DataForSEO per SERP e AI Overview (usa prima query)
            if ($input['apis']['dataforseo']['enabled'] && $input['apis']['dataforseo']['login']) {
                try {
                    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Calling DataForSEO\n", FILE_APPEND);
                    $dataforseo = new DataForSEOAPI(
                        $input['apis']['dataforseo']['login'],
                        $input['apis']['dataforseo']['password']
                    );
                    $results['serp_data'] = $dataforseo->getSerpAndAIOverview($queries_to_process[0]);
                } catch (Exception $e) {
                    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - DataForSEO error: " . $e->getMessage() . "\n", FILE_APPEND);
                    $results['serp_data'] = $this->getEmptySerpData();
                }
            } else {
                $results['serp_data'] = $this->getEmptySerpData();
            }
            
            // Step 3: Analisi comparativa (Sentiment Analysis + Topic/Entity Extraction)
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Performing comparative analysis\n", FILE_APPEND);
            $results['analysis'] = $this->performComparativeAnalysis($results);
            $results['metrics'] = $this->calculateMetrics($results);

            // Step 4: Analisi aggregata con GPT (BLOB completo)
            if ($input['apis']['openai']['enabled'] && $input['apis']['openai']['key']) {
                try {
                    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Starting aggregate GPT analysis\n", FILE_APPEND);
                    $analysisEngine = new AnalysisEngine($input['apis']['openai']['key']);
                    $results['aggregate_analysis'] = $analysisEngine->analyzeAggregateResponses(
                        $results['ai_responses'],
                        $input['brand'] ?? '',
                        $queries_to_process
                    );
                    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Aggregate analysis completed\n", FILE_APPEND);
                } catch (Exception $e) {
                    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Aggregate Analysis Error: " . $e->getMessage() . "\n", FILE_APPEND);
                    $results['aggregate_analysis'] = [];
                }
            }
            
            // Cache results
            file_put_contents($cache_file, json_encode($results));
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Analysis completed successfully\n", FILE_APPEND);
            
            return $results;
            
        } catch (Exception $e) {
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Analysis error: " . $e->getMessage() . "\n", FILE_APPEND);
            throw $e;
        }
    }
    
    private function processWithAllAIs($query, $apis, $brand) {
        $results = [];

        // Gemini - sia per fanout che come motore di risposta
        if ($apis['gemini']['enabled'] && $apis['gemini']['key']) {
            try {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Calling Gemini for response\n", FILE_APPEND);
                $gemini = new GeminiAPI($apis['gemini']['key'], $apis['gemini']['model']);
                $results['gemini'] = $gemini->query($query, $brand);
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini completed\n", FILE_APPEND);
            } catch (Exception $e) {
                $results['gemini'] = ['error' => 'Gemini Error: ' . $e->getMessage(), 'content' => ''];
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini Error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        // OpenAI
        if ($apis['openai']['enabled'] && $apis['openai']['key']) {
            try {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Calling OpenAI\n", FILE_APPEND);
                $openai = new OpenAIAPI($apis['openai']['key'], $apis['openai']['model']);
                $results['openai'] = $openai->query($query, $brand);
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - OpenAI completed\n", FILE_APPEND);
            } catch (Exception $e) {
                $results['openai'] = ['error' => 'OpenAI Error: ' . $e->getMessage(), 'content' => ''];
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - OpenAI Error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        // Claude
        if ($apis['claude']['enabled'] && $apis['claude']['key']) {
            try {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Calling Claude\n", FILE_APPEND);
                $claude = new ClaudeAPI($apis['claude']['key'], $apis['claude']['model']);
                $results['claude'] = $claude->query($query, $brand);
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Claude completed\n", FILE_APPEND);
            } catch (Exception $e) {
                $results['claude'] = ['error' => 'Claude Error: ' . $e->getMessage(), 'content' => ''];
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Claude Error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        // Grok
        if ($apis['grok']['enabled'] && $apis['grok']['key']) {
            try {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Calling Grok\n", FILE_APPEND);
                $grok = new GrokAPI($apis['grok']['key'], $apis['grok']['model']);
                $results['grok'] = $grok->query($query, $brand);
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Grok completed\n", FILE_APPEND);
            } catch (Exception $e) {
                $results['grok'] = ['error' => 'Grok Error: ' . $e->getMessage(), 'content' => ''];
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Grok Error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        // Perplexity
        if ($apis['perplexity']['enabled'] && $apis['perplexity']['key']) {
            try {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Calling Perplexity\n", FILE_APPEND);
                $perplexity = new PerplexityAPI($apis['perplexity']['key'], $apis['perplexity']['model']);
                $results['perplexity'] = $perplexity->query($query, $brand);
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Perplexity completed\n", FILE_APPEND);
            } catch (Exception $e) {
                $results['perplexity'] = ['error' => 'Perplexity Error: ' . $e->getMessage(), 'content' => ''];
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Perplexity Error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        // *** SECONDO LAYER GPT: Analisi intelligente di ogni risposta ***
        if ($apis['openai']['enabled'] && $apis['openai']['key']) {
            try {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Starting AI-powered analysis of responses\n", FILE_APPEND);
                $analysisEngine = new AnalysisEngine($apis['openai']['key']);

                foreach ($results as $ai_name => $response) {
                    if (!isset($response['error']) && isset($response['content']) && !empty($response['content'])) {
                        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Analyzing {$ai_name} response with GPT\n", FILE_APPEND);
                        $analysis = $analysisEngine->analyzeSingleResponse($ai_name, $response['content'], $brand, $query);

                        // Integra l'analisi nella risposta
                        $results[$ai_name]['sentiment'] = $analysis['sentiment'] ?? 0;
                        $results[$ai_name]['sentiment_label'] = $analysis['sentiment_label'] ?? 'neutrale';
                        $results[$ai_name]['topics'] = $analysis['topics'] ?? [];
                        $results[$ai_name]['domains'] = $analysis['domains'] ?? [];
                        $results[$ai_name]['entities'] = $analysis['entities'] ?? [];
                        $results[$ai_name]['brand_position'] = $analysis['brand_position'] ?? [];
                        $results[$ai_name]['key_insights'] = $analysis['key_insights'] ?? [];
                        $results[$ai_name]['tone'] = $analysis['tone'] ?? 'informativo';
                    } else {
                        // Risposta con errore o vuota
                        $results[$ai_name]['sentiment'] = 0;
                        $results[$ai_name]['sentiment_label'] = 'neutrale';
                        $results[$ai_name]['topics'] = [];
                        $results[$ai_name]['domains'] = [];
                        $results[$ai_name]['entities'] = [];
                        $results[$ai_name]['brand_position'] = [];
                        $results[$ai_name]['key_insights'] = [];
                        $results[$ai_name]['tone'] = 'errore';
                    }
                }

                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - AI-powered analysis completed\n", FILE_APPEND);
            } catch (Exception $e) {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Analysis Engine Error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        return $results;
    }
    
    private function performComparativeAnalysis($data) {
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Starting comparative analysis\n", FILE_APPEND);
        
        $analysis = [
            'domain_frequency' => [],
            'sentiment_analysis' => [],
            'topic_coverage' => [],
            'brand_positioning' => [],
            'entity_extraction' => []
        ];
        
        // Estrai domini, sentiment, topics da tutte le risposte di tutte le queries
        foreach ($data['ai_responses'] as $query_index => $query_results) {
            foreach ($query_results as $ai => $response) {
                // Domain extraction
                if (isset($response['domains']) && is_array($response['domains'])) {
                    foreach ($response['domains'] as $domain) {
                        $analysis['domain_frequency'][$domain] = 
                            ($analysis['domain_frequency'][$domain] ?? 0) + 1;
                    }
                }
                
                // Sentiment analysis accumulation
                if (isset($response['sentiment']) && is_numeric($response['sentiment'])) {
                    if (!isset($analysis['sentiment_analysis'][$ai])) {
                        $analysis['sentiment_analysis'][$ai] = [];
                    }
                    $analysis['sentiment_analysis'][$ai][] = $response['sentiment'];
                }
                
                // Topic/Entity coverage
                if (isset($response['topics']) && is_array($response['topics'])) {
                    if (!isset($analysis['topic_coverage'][$ai])) {
                        $analysis['topic_coverage'][$ai] = [];
                    }
                    $analysis['topic_coverage'][$ai] = array_merge($analysis['topic_coverage'][$ai], $response['topics']);
                    
                    // Entity extraction (topics are basic entities)
                    foreach ($response['topics'] as $topic) {
                        $analysis['entity_extraction'][$topic] = 
                            ($analysis['entity_extraction'][$topic] ?? 0) + 1;
                    }
                }
            }
        }
        
        // Calcola sentiment medio per AI model
        foreach ($analysis['sentiment_analysis'] as $ai => $sentiments) {
            if (!empty($sentiments)) {
                $analysis['sentiment_analysis'][$ai] = array_sum($sentiments) / count($sentiments);
            } else {
                $analysis['sentiment_analysis'][$ai] = 0;
            }
        }
        
        // Rimuovi duplicati dai topics per AI
        foreach ($analysis['topic_coverage'] as $ai => $topics) {
            $analysis['topic_coverage'][$ai] = array_unique($topics);
        }
        
        // Brand positioning analysis
        $brand = strtolower($data['brand']);
        if ($brand) {
            $analysis['brand_positioning'] = $this->analyzeBrandPositioning($data, $brand);
        }
        
        // Ordina per frequenza
        arsort($analysis['domain_frequency']);
        arsort($analysis['entity_extraction']);
        
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Comparative analysis completed\n", FILE_APPEND);
        
        return $analysis;
    }
    
    private function analyzeBrandPositioning($data, $brand) {
        $positioning = [
            'mentions_per_ai' => [],
            'context_sentiment' => [],
            'competitive_mentions' => []
        ];
        
        foreach ($data['ai_responses'] as $query_results) {
            foreach ($query_results as $ai => $response) {
                if (isset($response['content'])) {
                    $content = strtolower($response['content']);
                    $brand_mentions = substr_count($content, $brand);
                    
                    $positioning['mentions_per_ai'][$ai] = 
                        ($positioning['mentions_per_ai'][$ai] ?? 0) + $brand_mentions;
                    
                    // Context sentiment quando il brand è menzionato
                    if ($brand_mentions > 0) {
                        $positioning['context_sentiment'][$ai] = $response['sentiment'] ?? 0;
                    }
                }
            }
        }
        
        return $positioning;
    }
    
    private function calculateMetrics($data) {
        $metrics = [
            'total_responses' => 0,
            'unique_domains' => 0,
            'brand_mentions' => 0,
            'sentiment_score' => 0,
            'coverage_score' => 0,
            'entity_diversity' => 0,
            'ai_consensus' => 0
        ];
        
        // Conta risposte totali
        foreach ($data['ai_responses'] as $query_results) {
            $metrics['total_responses'] += count(array_filter($query_results, function($response) {
                return !isset($response['error']);
            }));
        }
        
        // Domini unici
        $metrics['unique_domains'] = count($data['analysis']['domain_frequency'] ?? []);
        
        // Menzioni brand totali
        $brand = strtolower($data['brand']);
        if ($brand) {
            foreach ($data['ai_responses'] as $query_results) {
                foreach ($query_results as $ai => $response) {
                    if (isset($response['content'])) {
                        $metrics['brand_mentions'] += substr_count(
                            strtolower($response['content']), 
                            $brand
                        );
                    }
                }
            }
        }
        
        // Sentiment score medio globale
        $sentiments = array_values($data['analysis']['sentiment_analysis'] ?? []);
        $valid_sentiments = array_filter($sentiments, 'is_numeric');
        $metrics['sentiment_score'] = !empty($valid_sentiments) ? 
            array_sum($valid_sentiments) / count($valid_sentiments) : 0;
        
        // Coverage score basato su numero di topic unici trovati
        $all_topics = [];
        foreach ($data['analysis']['topic_coverage'] ?? [] as $ai => $topics) {
            $all_topics = array_merge($all_topics, $topics);
        }
        $metrics['coverage_score'] = count(array_unique($all_topics));
        
        // Entity diversity (varietà entità estratte)
        $metrics['entity_diversity'] = count($data['analysis']['entity_extraction'] ?? []);
        
        // AI Consensus (quanto concordano gli AI models)
        $metrics['ai_consensus'] = $this->calculateConsensus($data);
        
        return $metrics;
    }
    
    private function calculateConsensus($data) {
        // Calcola quanto sono simili le risposte degli AI models
        $consensus_score = 0;
        $comparisons = 0;
        
        foreach ($data['ai_responses'] as $query_results) {
            $ai_models = array_keys($query_results);
            $sentiment_values = [];
            
            foreach ($ai_models as $ai) {
                if (isset($query_results[$ai]['sentiment']) && is_numeric($query_results[$ai]['sentiment'])) {
                    $sentiment_values[] = $query_results[$ai]['sentiment'];
                }
            }
            
            if (count($sentiment_values) > 1) {
                // Calcola varianza dei sentiment
                $mean = array_sum($sentiment_values) / count($sentiment_values);
                $variance = array_sum(array_map(function($x) use ($mean) {
                    return pow($x - $mean, 2);
                }, $sentiment_values)) / count($sentiment_values);
                
                // Consensus più alto = varianza più bassa
                $consensus_score += 1 - min($variance, 1);
                $comparisons++;
            }
        }
        
        return $comparisons > 0 ? $consensus_score / $comparisons : 0;
    }
    
    private function getEmptySerpData() {
        return [
            'serp_results' => [],
            'ai_overview' => [
                'has_ai_overview' => false,
                'ai_content' => 'Dati SERP non disponibili',
                'sources_cited' => []
            ]
        ];
    }
}

// Main execution
try {
    $input = file_get_contents('php://input');
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Raw input received: " . substr($input, 0, 200) . "\n", FILE_APPEND);
    
    $input = json_decode($input, true);
    
    if (!$input) {
        throw new Exception('Input JSON non valido: ' . json_last_error_msg());
    }
    
    // Validazione input
    if (isset($input['action']) && $input['action'] === 'fanout_only') {
        // Per fanout serve solo la query originale
        if (!isset($input['query']) || empty(trim($input['query']))) {
            throw new Exception('Query mancante per generazione fanout');
        }
    } else {
        // Per analisi completa serve queries array o query singola
        if (!isset($input['queries']) && !isset($input['query'])) {
            throw new Exception('Queries mancanti per analisi');
        }
        if (isset($input['queries']) && (!is_array($input['queries']) || empty($input['queries']))) {
            throw new Exception('Array queries vuoto o non valido');
        }
    }
    
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Input validated successfully\n", FILE_APPEND);
    
    $analyzer = new AIAnalyzer();
    $results = $analyzer->process($input);
    
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Sending response\n", FILE_APPEND);
    echo json_encode($results);
    
} catch (Exception $e) {
    $error = [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s'),
        'trace' => $e->getTraceAsString()
    ];
    
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Fatal error: " . json_encode($error) . "\n", FILE_APPEND);
    
    http_response_code(400);
    echo json_encode($error);
}
?>