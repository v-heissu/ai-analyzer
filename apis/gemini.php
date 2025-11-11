<?php
class GeminiAPI {
    private $api_key;
    private $model;
    private $base_url = 'https://generativelanguage.googleapis.com/v1/models/';
    
    public function __construct($api_key, $model = 'gemini-1.5-flash') {
        $this->api_key = $api_key;
        $this->model = $model;
    }
    
    public function generateFanOutQueries($keyword, $brand = '') {
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini generateFanOutQueries called with keyword: {$keyword}\n", FILE_APPEND);

        $brand_context = $brand ? " nel contesto del brand/settore '{$brand}'" : "";

        $prompt = "Sei un esperto di SEO e analisi delle query di ricerca.

KEYWORD DA ANALIZZARE: \"{$keyword}\"{$brand_context}

OBIETTIVO:
Genera 8 query di ricerca semanticamente correlate che un utente potrebbe fare dopo aver cercato questa keyword.
Pensa alle domande successive, approfondimenti, alternative, confronti che emergerebbero in una conversazione con un AI assistant.

FORMATO RICHIESTO:
Restituisci SOLO le query, una per riga, senza numerazione, senza spiegazioni.
Ogni query deve essere una frase completa e specifica.

ESEMPI DI PATTERN:
- \"Come funziona [keyword]\"
- \"Quali sono i vantaggi di [keyword]\"
- \"[keyword] vs alternative\"
- \"Migliori [keyword] per [caso d'uso]\"
- \"Quanto costa [keyword]\"
- \"Come scegliere [keyword]\"
- \"[keyword] per principianti\"
- \"Dove trovare [keyword]\"

GENERA LE 8 QUERY:";

        $response = $this->makeRequest($prompt);

        if (!$response) {
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini: makeRequest returned null\n", FILE_APPEND);
            return [];
        }

        // Log della risposta per debug
        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini response structure: " . json_encode(array_keys($response)) . "\n", FILE_APPEND);

        if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $text = trim($response['candidates'][0]['content']['parts'][0]['text']);
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini generated text: " . substr($text, 0, 200) . "...\n", FILE_APPEND);

            // Parse le query - ogni riga è una query
            $queries = explode("\n", $text);
            $queries = array_map('trim', $queries);

            // Rimuovi linee vuote e numeri iniziali
            $queries = array_filter($queries, function($q) {
                return !empty($q) && strlen($q) > 5;
            });

            // Rimuovi numerazione se presente (1. 2. • - etc)
            $queries = array_map(function($q) {
                return preg_replace('/^[\d\.\-\•\*\:\)\]\}]+\s*/', '', $q);
            }, $queries);

            $queries = array_values($queries); // Re-index

            // Prendi max 8
            if (count($queries) > 8) {
                $queries = array_slice($queries, 0, 8);
            }

            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini parsed " . count($queries) . " queries\n", FILE_APPEND);
            return $queries;
        } else {
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini: Invalid response structure. Full response: " . json_encode($response) . "\n", FILE_APPEND);
        }

        return [];
    }
    
    public function query($query, $brand = '') {
        $prompt = "Rispondi alla domanda: {$query}" .
                  ($brand ? " (considera il brand {$brand} se pertinente)" : "");

        $response = $this->makeRequest($prompt);

        $result = [
            'content' => '',
            'raw_response' => $response
        ];

        if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $result['content'] = $response['candidates'][0]['content']['parts'][0]['text'];
        }

        return $result;
    }
    
    private function makeRequest($prompt) {
        if (!$this->api_key) {
            error_log("Gemini: API key missing");
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini: API key missing\n", FILE_APPEND);
            return null;
        }

        $url = $this->base_url . $this->model . ':generateContent?key=' . $this->api_key;

        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini: Making request to model {$this->model}\n", FILE_APPEND);

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'topK' => 20,
                'topP' => 0.9,
                'maxOutputTokens' => 2048
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            error_log("Gemini CURL Error: " . $error);
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini CURL Error: {$error}\n", FILE_APPEND);
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini HTTP Status: {$http_code}\n", FILE_APPEND);

        if ($http_code === 200) {
            $decoded = json_decode($response, true);

            if ($decoded === null) {
                error_log("Gemini: Invalid JSON response");
                file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini: Invalid JSON response. Raw: " . substr($response, 0, 500) . "\n", FILE_APPEND);
                return null;
            }

            return $decoded;
        } else {
            error_log("Gemini HTTP Error {$http_code}: " . substr($response, 0, 200));
            file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Gemini HTTP Error {$http_code}: {$response}\n", FILE_APPEND);
        }

        return null;
    }
    
}
?>