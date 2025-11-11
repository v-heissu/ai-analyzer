<?php
/**
 * AnalysisEngine - Secondo layer GPT per analisi intelligente
 *
 * Usa GPT-4o per analizzare le risposte AI e estrarre:
 * - Sentiment analysis
 * - Topic extraction
 * - Brand positioning
 * - Entity recognition
 */
class AnalysisEngine {
    private $openai_key;
    private $model = 'gpt-4o-mini'; // Più economico ma efficace per analisi
    private $base_url = 'https://api.openai.com/v1/chat/completions';

    public function __construct($openai_key) {
        $this->openai_key = $openai_key;
    }

    /**
     * Analizza una singola risposta AI
     * Estrae: sentiment, topics, domains, brand_position
     */
    public function analyzeSingleResponse($ai_name, $content, $brand = '', $query = '') {
        if (empty($content)) {
            return $this->getEmptyAnalysis();
        }

        $prompt = $this->buildSingleResponsePrompt($ai_name, $content, $brand, $query);
        $response = $this->makeGPTRequest($prompt);

        if ($response && isset($response['choices'][0]['message']['content'])) {
            try {
                $analysis = json_decode($response['choices'][0]['message']['content'], true);
                if ($analysis && is_array($analysis)) {
                    return $analysis;
                }
            } catch (Exception $e) {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - JSON parse error in analyzeSingleResponse: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        return $this->getEmptyAnalysis();
    }

    /**
     * Analizza il blob completo di tutte le risposte
     * Fornisce insights aggregati e comparativi
     */
    public function analyzeAggregateResponses($all_responses, $brand = '', $queries = []) {
        if (empty($all_responses)) {
            return $this->getEmptyAggregateAnalysis();
        }

        $prompt = $this->buildAggregatePrompt($all_responses, $brand, $queries);
        $response = $this->makeGPTRequest($prompt, 4000); // Più token per analisi aggregata

        if ($response && isset($response['choices'][0]['message']['content'])) {
            try {
                $analysis = json_decode($response['choices'][0]['message']['content'], true);
                if ($analysis && is_array($analysis)) {
                    return $analysis;
                }
            } catch (Exception $e) {
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - JSON parse error in analyzeAggregateResponses: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        return $this->getEmptyAggregateAnalysis();
    }

    /**
     * Costruisce il prompt per analisi singola risposta
     */
    private function buildSingleResponsePrompt($ai_name, $content, $brand, $query) {
        $brand_instruction = $brand ? "Analizza anche la posizione e il contesto del brand '{$brand}' nella risposta." : "";

        return <<<PROMPT
Sei un analista esperto di AI e sentiment analysis. Analizza la seguente risposta generata da {$ai_name}.

QUERY ORIGINALE: {$query}

RISPOSTA DA ANALIZZARE:
{$content}

{$brand_instruction}

Fornisci un'analisi strutturata in formato JSON con i seguenti campi:

{
  "sentiment": <numero da -1 a 1, dove -1=molto negativo, 0=neutrale, 1=molto positivo>,
  "sentiment_label": "<positivo/neutrale/negativo>",
  "topics": ["topic1", "topic2", "topic3"],
  "domains": ["dominio1.com", "dominio2.com"],
  "entities": ["entità1", "entità2"],
  "brand_position": {
    "mentioned": <true/false>,
    "position_in_text": "<inizio/metà/fine/non_menzionato>",
    "context": "<positivo/neutrale/negativo/non_applicabile>",
    "prominence": <numero 0-10, dove 10=molto prominente>
  },
  "key_insights": ["insight1", "insight2"],
  "tone": "<formale/informativo/promozionale/tecnico/altro>"
}

ISTRUZIONI:
- Sentiment: valuta il tono generale e l'atteggiamento della risposta
- Topics: estrai 3-5 argomenti principali trattati
- Domains: identifica domini/siti web citati (solo il dominio, es: "example.com")
- Entities: estrai nomi di aziende, prodotti, persone, tecnologie menzionate
- Brand position: analizza come viene posizionato il brand (se presente)
- Key insights: 2-3 insight chiave dalla risposta
- Tone: identifica il tono della risposta

Rispondi SOLO con il JSON, senza altro testo.
PROMPT;
    }

    /**
     * Costruisce il prompt per analisi aggregata
     */
    private function buildAggregatePrompt($all_responses, $brand, $queries) {
        $queries_text = is_array($queries) ? implode(", ", $queries) : $queries;
        $brand_instruction = $brand ? "Focalizzati su come il brand '{$brand}' viene rappresentato nell'insieme." : "";

        // Costruisci summary delle risposte
        $responses_summary = "";
        foreach ($all_responses as $query_index => $query_results) {
            $responses_summary .= "\n--- QUERY " . ($query_index + 1) . " ---\n";
            foreach ($query_results as $ai_name => $response) {
                $content_preview = isset($response['content']) ?
                    substr($response['content'], 0, 500) . "..." :
                    "Nessun contenuto";
                $responses_summary .= "{$ai_name}: {$content_preview}\n\n";
            }
        }

        return <<<PROMPT
Sei un analista senior di AI landscape e competitive intelligence. Analizza il LANDSCAPE COMPLETO delle risposte AI.

QUERIES ANALIZZATE: {$queries_text}

RISPOSTE DA TUTTI GLI AI MODELS:
{$responses_summary}

{$brand_instruction}

Fornisci un'analisi strategica in formato JSON:

{
  "overall_sentiment": <numero -1 a 1>,
  "sentiment_variance": <numero 0-1, quanto variano i sentiment tra AI>,
  "consensus_level": <numero 0-1, quanto concordano gli AI>,
  "dominant_topics": ["topic1", "topic2", "topic3"],
  "topic_diversity": <numero 0-10>,
  "most_cited_domains": ["domain1.com", "domain2.com"],
  "domain_concentration": <numero 0-1, quanto concentrati sono i domini>,
  "ai_model_differences": {
    "openai": "<caratteristica distintiva>",
    "gemini": "<caratteristica distintiva>",
    "claude": "<caratteristica distintiva>",
    "grok": "<caratteristica distintiva>",
    "perplexity": "<caratteristica distintiva>"
  },
  "brand_landscape": {
    "overall_visibility": <numero 0-10>,
    "positioning_consistency": <numero 0-10>,
    "competitive_context": ["competitor1", "competitor2"],
    "recommendation": "<strategic insight>"
  },
  "strategic_insights": [
    "<insight strategico 1>",
    "<insight strategico 2>",
    "<insight strategico 3>"
  ],
  "opportunities": ["opportunità1", "opportunità2"],
  "risks": ["rischio1", "rischio2"]
}

ISTRUZIONI:
- Analizza pattern e differenze tra gli AI models
- Identifica consensus e divergenze
- Fornisci insights strategici per C-level executives
- Focalizzati su opportunità e rischi per il brand

Rispondi SOLO con il JSON, senza altro testo.
PROMPT;
    }

    /**
     * Esegue chiamata a GPT-4o
     */
    private function makeGPTRequest($prompt, $max_tokens = 2048) {
        if (!$this->openai_key) return null;

        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Sei un analista esperto di AI e competitive intelligence. Rispondi sempre in formato JSON valido.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => $max_tokens,
            'response_format' => ['type' => 'json_object']
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->openai_key
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - CURL error: " . curl_error($ch) . "\n", FILE_APPEND);
        }

        curl_close($ch);

        if ($http_code === 200) {
            return json_decode($response, true);
        }

        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - GPT API error: HTTP {$http_code} - {$response}\n", FILE_APPEND);
        return null;
    }

    /**
     * Struttura vuota per singola analisi
     */
    private function getEmptyAnalysis() {
        return [
            'sentiment' => 0,
            'sentiment_label' => 'neutrale',
            'topics' => [],
            'domains' => [],
            'entities' => [],
            'brand_position' => [
                'mentioned' => false,
                'position_in_text' => 'non_menzionato',
                'context' => 'non_applicabile',
                'prominence' => 0
            ],
            'key_insights' => [],
            'tone' => 'informativo'
        ];
    }

    /**
     * Struttura vuota per analisi aggregata
     */
    private function getEmptyAggregateAnalysis() {
        return [
            'overall_sentiment' => 0,
            'sentiment_variance' => 0,
            'consensus_level' => 0,
            'dominant_topics' => [],
            'topic_diversity' => 0,
            'most_cited_domains' => [],
            'domain_concentration' => 0,
            'ai_model_differences' => [],
            'brand_landscape' => [
                'overall_visibility' => 0,
                'positioning_consistency' => 0,
                'competitive_context' => [],
                'recommendation' => 'Dati insufficienti per analisi'
            ],
            'strategic_insights' => [],
            'opportunities' => [],
            'risks' => []
        ];
    }
}
?>
