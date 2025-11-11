<?php
class PerplexityAPI {
    private $api_key;
    private $model;
    private $base_url = 'https://api.perplexity.ai/chat/completions';

    public function __construct($api_key, $model = 'sonar-pro') {
        $this->api_key = $api_key;
        $this->model = $model;
    }

    public function query($query, $brand = '') {
        $prompt = $query;
        if ($brand) {
            $prompt .= " (considera il brand {$brand} se pertinente)";
        }

        $response = $this->makeRequest($prompt);

        $result = [
            'content' => '',
            'citations' => [],
            'raw_response' => $response
        ];

        if ($response && isset($response['choices'][0]['message']['content'])) {
            $result['content'] = $response['choices'][0]['message']['content'];

            // Perplexity restituisce citazioni
            if (isset($response['citations'])) {
                $result['citations'] = $response['citations'];
            }
        }

        return $result;
    }

    private function makeRequest($prompt) {
        if (!$this->api_key) return null;

        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 2048
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
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
