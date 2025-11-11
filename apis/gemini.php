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
            'domains' => [],
            'sentiment' => 0,
            'topics' => [],
            'raw_response' => $response
        ];
        
        if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $response['candidates'][0]['content']['parts'][0]['text'];
            $result['content'] = $content;
            $result['domains'] = $this->extractDomains($content);
            $result['sentiment'] = $this->analyzeSentiment($content);
            $result['topics'] = $this->extractTopics($content);
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
    
    private function extractDomains($text) {
        $pattern = '/(?:https?:\/\/)?(?:www\.)?([a-zA-Z0-9-]+(?:\.[a-zA-Z]{2,})+)/';
        preg_match_all($pattern, $text, $matches);
        return array_unique($matches[1] ?? []);
    }
    
    private function analyzeSentiment($text) {
        // Sentiment basic basato su keyword
        $positive_words = ['buono', 'ottimo', 'eccellente', 'migliore', 'fantastico'];
        $negative_words = ['cattivo', 'pessimo', 'terribile', 'peggiore', 'orribile'];
        
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count(strtolower($text), $word);
        }
        
        foreach ($negative_words as $word) {
            $negative_count += substr_count(strtolower($text), $word);
        }
        
        if ($positive_count > $negative_count) return 1;
        if ($negative_count > $positive_count) return -1;
        return 0;
    }
    
    private function extractTopics($text) {
        // Topic extraction basic basato su POS tagging simulato
        $words = str_word_count(strtolower($text), 1);
        $topics = [];
        
        // Filtra parole significative (lunghezza > 4)
        foreach ($words as $word) {
            if (strlen($word) > 4 && !in_array($word, ['della', 'delle', 'sono', 'viene', 'hanno'])) {
                $topics[] = $word;
            }
        }
        
        // Prendi i 5 topic più comuni
        $topic_counts = array_count_values($topics);
        arsort($topic_counts);
        return array_keys(array_slice($topic_counts, 0, 5));
    }
}
?>