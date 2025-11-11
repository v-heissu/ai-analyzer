<?php
class DataForSEOAPI {
    private $login;
    private $password;
    private $base_url = 'https://api.dataforseo.com/v3/serp/';
    
    public function __construct($login, $password) {
        $this->login = $login;
        $this->password = $password;
    }
    
    public function getSerpAndAIOverview($query) {
        if (!$this->login || !$this->password) {
            return $this->getEmptyResult();
        }
        
        $result = [
            'serp_results' => $this->getSerpResults($query),
            'ai_overview' => $this->getAIOverview($query)
        ];
        
        return $result;
    }
    
    private function getSerpResults($query) {
        $endpoint = 'google/organic/live/advanced';
        
        $data = [
            [
                'keyword' => $query,
                'location_code' => 2380, // Italy
                'language_code' => 'it',
                'device' => 'desktop',
                'os' => 'windows',
                'depth' => 10
            ]
        ];
        
        $response = $this->makeRequest($endpoint, $data);
        
        if ($response && isset($response['tasks'][0]['result'][0]['items'])) {
            return array_map(function($item) {
                return [
                    'position' => $item['rank_absolute'] ?? 0,
                    'title' => $item['title'] ?? '',
                    'url' => $item['url'] ?? '',
                    'domain' => parse_url($item['url'] ?? '', PHP_URL_HOST),
                    'snippet' => $item['description'] ?? ''
                ];
            }, $response['tasks'][0]['result'][0]['items']);
        }
        
        return [];
    }
    
    private function getAIOverview($query) {
        // Simulazione AI Overview (DataForSEO non ha ancora API dedicata)
        // In una implementazione reale, potresti usare Google Search API o altre fonti
        
        return [
            'has_ai_overview' => rand(0, 1),
            'ai_content' => 'Simulated AI Overview content for: ' . $query,
            'sources_cited' => [
                'wikipedia.org',
                'example.com',
                'test.it'
            ]
        ];
    }
    
    private function makeRequest($endpoint, $data) {
        $url = $this->base_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $this->login . ':' . $this->password);
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
    
    private function getEmptyResult() {
        return [
            'serp_results' => [],
            'ai_overview' => [
                'has_ai_overview' => false,
                'ai_content' => 'Credenziali DataForSEO non configurate',
                'sources_cited' => []
            ]
        ];
    }
}
?>