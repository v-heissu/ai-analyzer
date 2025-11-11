<?php
class OpenAIAPI {
    private $api_key;
    private $model;
    private $base_url = 'https://api.openai.com/v1/chat/completions';
    
    public function __construct($api_key, $model = 'gpt-4o-search-preview') {
        $this->api_key = $api_key;
        $this->model = $model;
    }
    
    public function query($query, $brand = '') {
        if (!$this->api_key) return $this->getEmptyResult();
        
        $prompt = "Rispondi alla domanda: {$query}" . 
                  ($brand ? " (considera il brand {$brand} se pertinente)" : "");
        
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.3
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
        
        $result = [
            'content' => '',
            'domains' => [],
            'sentiment' => 0,
            'topics' => [],
            'raw_response' => null
        ];
        
        if ($http_code === 200) {
            $decoded = json_decode($response, true);
            if (isset($decoded['choices'][0]['message']['content'])) {
                $content = $decoded['choices'][0]['message']['content'];
                $result['content'] = $content;
                $result['domains'] = $this->extractDomains($content);
                $result['sentiment'] = $this->analyzeSentiment($content);
                $result['topics'] = $this->extractTopics($content);
                $result['raw_response'] = $decoded;
            }
        }
        
        return $result;
    }
    
    private function getEmptyResult() {
        return [
            'content' => 'API Key non configurata',
            'domains' => [],
            'sentiment' => 0,
            'topics' => [],
            'raw_response' => null
        ];
    }
    
    private function extractDomains($text) {
        $pattern = '/(?:https?:\/\/)?(?:www\.)?([a-zA-Z0-9-]+(?:\.[a-zA-Z]{2,})+)/';
        preg_match_all($pattern, $text, $matches);
        return array_unique($matches[1] ?? []);
    }
    
    private function analyzeSentiment($text) {
        $positive_words = ['good', 'great', 'excellent', 'best', 'amazing', 'buono', 'ottimo'];
        $negative_words = ['bad', 'terrible', 'worst', 'awful', 'cattivo', 'pessimo'];
        
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
        $words = str_word_count(strtolower($text), 1);
        $stop_words = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'had', 'her', 'was', 'one', 'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'man', 'new', 'now', 'old', 'see', 'two', 'way', 'who', 'boy', 'did', 'its', 'let', 'put', 'say', 'she', 'too', 'use'];
        
        $topics = [];
        foreach ($words as $word) {
            if (strlen($word) > 4 && !in_array($word, $stop_words)) {
                $topics[] = $word;
            }
        }
        
        $topic_counts = array_count_values($topics);
        arsort($topic_counts);
        return array_keys(array_slice($topic_counts, 0, 5));
    }
}
?>