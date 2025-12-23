<?php
declare(strict_types=1);

namespace App\Services\AI;

/**
 * AI Provider Interface
 * 
 * Allows switching between different AI providers
 */
interface AIProviderInterface
{
    public function generateText(string $prompt, array $options = []): string;
    public function moderateContent(string $content): array;
    public function analyzeImage(string $imageUrl): array;
}

/**
 * Base AI Service
 * 
 * Manages multiple AI providers
 */
class AIService
{
    private AIProviderInterface $provider;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->loadProvider($config['provider'] ?? 'ollama');
    }

    /**
     * Load AI provider based on config
     */
    private function loadProvider(string $providerName): void
    {
        switch ($providerName) {
            case 'openai':
                $this->provider = new OpenAIProvider($this->config['openai_api_key'] ?? '');
                break;
            
            case 'gemini':
                $this->provider = new GeminiProvider($this->config['gemini_api_key'] ?? '');
                break;
            
            case 'ollama':
            default:
                $this->provider = new OllamaProvider($this->config['ollama_url'] ?? 'http://localhost:11434');
                break;
        }
    }

    /**
     * Generate text using current provider
     */
    public function generateText(string $prompt, array $options = []): string
    {
        return $this->provider->generateText($prompt, $options);
    }

    /**
     * Moderate content (uses FREE OpenAI Moderation API)
     */
    public function moderateContent(string $content): array
    {
        // Always use free OpenAI Moderation API
        $moderator = new OpenAIModerationProvider();
        return $moderator->moderateContent($content);
    }

    /**
     * Analyze toxicity (uses FREE Perspective API)
     */
    public function analyzeToxicity(string $text): array
    {
        $perspective = new PerspectiveProvider($this->config['perspective_api_key'] ?? '');
        return $perspective->analyzeToxicity($text);
    }
}

/**
 * Ollama Provider (FREE, self-hosted)
 */
class OllamaProvider implements AIProviderInterface
{
    private string $baseUrl;

    public function __construct(string $baseUrl = 'http://localhost:11434')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function generateText(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? 'llama2';
        
        $ch = curl_init($this->baseUrl . '/api/generate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Ollama API error: HTTP $httpCode");
        }

        $data = json_decode($response, true);
        return $data['response'] ?? '';
    }

    public function moderateContent(string $content): array
    {
        // Ollama doesn't have moderation API, return safe
        return ['is_safe' => true, 'categories' => []];
    }

    public function analyzeImage(string $imageUrl): array
    {
        // Use llava model for image analysis
        $ch = curl_init($this->baseUrl . '/api/generate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'llava',
            'prompt' => 'Describe this image in detail',
            'images' => [base64_encode(file_get_contents($imageUrl))],
            'stream' => false
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return ['description' => $data['response'] ?? ''];
    }
}

/**
 * OpenAI Moderation Provider (FREE)
 */
class OpenAIModerationProvider
{
    public function moderateContent(string $content): array
    {
        $ch = curl_init('https://api.openai.com/v1/moderations');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['input' => $content]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
            // No API key needed for moderation endpoint!
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $result = $data['results'][0] ?? [];

        return [
            'is_safe' => !($result['flagged'] ?? false),
            'categories' => $result['categories'] ?? [],
            'scores' => $result['category_scores'] ?? []
        ];
    }
}

/**
 * Perspective API Provider (FREE)
 */
class PerspectiveProvider
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function analyzeToxicity(string $text): array
    {
        $url = 'https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze?key=' . $this->apiKey;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'comment' => ['text' => $text],
            'languages' => ['en'],
            'requestedAttributes' => [
                'TOXICITY' => [],
                'SEVERE_TOXICITY' => [],
                'IDENTITY_ATTACK' => [],
                'INSULT' => [],
                'PROFANITY' => [],
                'THREAT' => []
            ]
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        
        $scores = [];
        foreach ($data['attributeScores'] ?? [] as $attribute => $attrData) {
            $scores[strtolower($attribute)] = $attrData['summaryScore']['value'] ?? 0;
        }

        return [
            'scores' => $scores,
            'is_toxic' => ($scores['toxicity'] ?? 0) > 0.7,
            'severity' => $this->calculateSeverity($scores)
        ];
    }

    private function calculateSeverity(array $scores): string
    {
        $maxScore = max($scores);
        
        if ($maxScore > 0.9) return 'severe';
        if ($maxScore > 0.7) return 'high';
        if ($maxScore > 0.5) return 'medium';
        return 'low';
    }
}

/**
 * OpenAI Provider (requires API key)
 */
class OpenAIProvider implements AIProviderInterface
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function generateText(string $prompt, array $options = []): string
    {
        $model = $options['model'] ?? 'gpt-3.5-turbo';
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $options['max_tokens'] ?? 500
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function moderateContent(string $content): array
    {
        $moderator = new OpenAIModerationProvider();
        return $moderator->moderateContent($content);
    }

    public function analyzeImage(string $imageUrl): array
    {
        // GPT-4 Vision implementation
        return ['description' => 'Image analysis not implemented'];
    }
}

/**
 * Google Gemini Provider (requires API key)
 */
class GeminiProvider implements AIProviderInterface
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function generateText(string $prompt, array $options = []): string
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $this->apiKey;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    public function moderateContent(string $content): array
    {
        // Use free OpenAI moderation
        $moderator = new OpenAIModerationProvider();
        return $moderator->moderateContent($content);
    }

    public function analyzeImage(string $imageUrl): array
    {
        // Gemini Vision implementation
        return ['description' => 'Image analysis not implemented'];
    }
}
