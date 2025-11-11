<?php
class ClaudeAPI {
    private $api_key;
    private $model;
    private $base_url = 'https://api.anthropic.com/v1/messages';
    
    public function __construct($api_key, $model = 'claude-sonnet-4-5-20250929') {
        $this->api_key = $api_key;
        $this->model = $model;
    }
    
    public function query($query, $brand = '') {
        if (!$this->api_key) return $this->getEmptyResult();
        
        $prompt = "Rispondi alla domanda: {$query}" . 
                  ($brand ? " (considera il brand {$brand} se pertinente)" : "");
        
        $data = [
            'model' => $this->model,
            'max_tokens' => 2000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->api_key,
            'anthropic-version: 2023-06-01'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = [
            'content' => '',
            'raw_response' => null
        ];

        if ($http_code === 200) {
            $decoded = json_decode($response, true);
            if (isset($decoded['content'][0]['text'])) {
                $result['content'] = $decoded['content'][0]['text'];
                $result['raw_response'] = $decoded;
            }
        }

        return $result;
    }

    private function getEmptyResult() {
        return [
            'content' => 'API Key non configurata',
            'raw_response' => null
        ];
    }
}
?>
