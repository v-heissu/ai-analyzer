<?php
class GeminiAPI {
    private $api_key;
    private $model;
    private $base_url = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    public function __construct($api_key, $model = 'gemini-2.5-flash') {
        $this->api_key = $api_key;
        $this->model = $model;
    }
    
    public function generateFanOutQueries($keyword, $brand = '') {
        $prompt = "Genera 8 sotto-query semantiche per la keyword '{$keyword}'" . 
                  ($brand ? " considerando il brand '{$brand}'" : "") . 
                  ". Restituisci solo le query, una per riga, senza numerazione.";
        
        $response = $this->makeRequest($prompt);
        
        if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $queries = explode("\n", trim($response['candidates'][0]['content']['parts'][0]['text']));
            return array_filter(array_map('trim', $queries));
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
        if (!$this->api_key) return null;
        
        $url = $this->base_url . $this->model . ':generateContent?key=' . $this->api_key;
        
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
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        }
        
        return null;
    }
    
}
?>