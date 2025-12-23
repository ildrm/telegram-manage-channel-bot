<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Services\AI\AIService;

/**
 * Content Intelligence Service
 * 
 * AI-powered content features
 */
class ContentIntelligenceService
{
    private Database $db;
    private AIService $ai;

    public function __construct(Database $db, AIService $ai)
    {
        $this->db = $db;
        $this->ai = $ai;
    }

    /**
     * Generate caption for image
     */
    public function generateCaption(string $imageUrl, string $style = 'engaging'): string
    {
        $prompt = "Generate a {$style} caption for this social media post. Keep it under 200 characters.";
        
        try {
            return $this->ai->generateText($prompt);
        } catch (\Exception $e) {
            error_log("Caption generation failed: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Suggest hashtags
     */
    public function suggestHashtags(string $content, int $count = 5): array
    {
        $prompt = "Suggest {$count} relevant hashtags for this post:\n\n{$content}\n\nReturn only the hashtags, one per line, with # prefix.";
        
        try {
            $response = $this->ai->generateText($prompt);
            $hashtags = array_filter(array_map('trim', explode("\n", $response)));
            return array_slice($hashtags, 0, $count);
        } catch (\Exception $e) {
            error_log("Hashtag suggestion failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Improve content
     */
    public function improveContent(string $content): array
    {
        $prompt = "Improve this social media post for better engagement. Provide 3 different variations:\n\n{$content}";
        
        try {
            $response = $this->ai->generateText($prompt);
            // Parse variations
            $variations = array_filter(array_map('trim', preg_split('/\n\n+/', $response)));
            return array_slice($variations, 0, 3);
        } catch (\Exception $e) {
            error_log("Content improvement failed: " . $e->getMessage());
            return [$content];
        }
    }

    /**
     * Moderate content before posting
     */
    public function moderateBeforePost(string $content): array
    {
        try {
            // Use FREE OpenAI Moderation
            $moderation = $this->ai->moderateContent($content);
            
            // Use FREE Perspective API for toxicity
            $toxicity = $this->ai->analyzeToxicity($content);
            
            $issues = [];
            
            if (!$moderation['is_safe']) {
                foreach ($moderation['categories'] as $category => $flagged) {
                    if ($flagged) {
                        $issues[] = ucfirst(str_replace('_', ' ', $category));
                    }
                }
            }
            
            if ($toxicity['is_toxic']) {
                $issues[] = 'Toxic language detected';
            }
            
            return [
                'is_safe' => empty($issues),
                'issues' => $issues,
                'moderation' => $moderation,
                'toxicity' => $toxicity
            ];
        } catch (\Exception $e) {
            error_log("Moderation failed: " . $e->getMessage());
            return ['is_safe' => true, 'issues' => []]; // Fail open
        }
    }

    /**
     * Analyze topic/category
     */
    public function analyzeTopic(string $content): array
    {
        $prompt = "Analyze this post and identify: 1) Main topic, 2) Category (News/Entertainment/Education/Business/etc), 3) Sentiment (positive/neutral/negative)\n\n{$content}";
        
        try {
            $response = $this->ai->generateText($prompt);
            
            // Parse response
            return [
                'topic' => $this->extractTopic($response),
                'category' => $this->extractCategory($response),
                'sentiment' => $this->extractSentiment($response)
            ];
        } catch (\Exception $e) {
            return ['topic' => 'Unknown', 'category' => 'General', 'sentiment' => 'neutral'];
        }
    }

    private function extractTopic(string $text): string
    {
        if (preg_match('/topic[:\s]+(.+?)(?:\n|$)/i', $text, $matches)) {
            return trim($matches[1]);
        }
        return 'Unknown';
    }

    private function extractCategory(string $text): string
    {
        if (preg_match('/category[:\s]+(.+?)(?:\n|$)/i', $text, $matches)) {
            return trim($matches[1]);
        }
        return 'General';
    }

    private function extractSentiment(string $text): string
    {
        if (preg_match('/sentiment[:\s]+(.+?)(?:\n|$)/i', $text, $matches)) {
            return strtolower(trim($matches[1]));
        }
        return 'neutral';
    }
}
