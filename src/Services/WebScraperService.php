<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Web Scraper Service
 * 
 * Extract content from websites
 */
class WebScraperService
{
    /**
     * Scrape article from URL
     */
    public function scrapeArticle(string $url): ?array
    {
        try {
            $html = $this->fetchUrl($url);
            
            if (!$html) {
                return null;
            }

            // Extract metadata
            $title = $this->extractTitle($html);
            $description = $this->extractDescription($html);
            $image = $this->extractImage($html, $url);
            $content = $this->extractContent($html);

            return [
                'url' => $url,
                'title' => $title,
                'description' => $description,
                'image' => $image,
                'content' => $content,
                'scraped_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            error_log("Scraping failed for $url: " . $e->getMessage());
            return null;
        }
    }

    private function fetchUrl(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; TelegramBot/1.0)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200) ? $html : null;
    }

    private function extractTitle(string $html): string
    {
        // Try Open Graph first
        if (preg_match('/<meta\s+property="og:title"\s+content="([^"]+)"/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        
        // Try regular title tag
        if (preg_match('/<title>([^<]+)<\/title>/i', $html, $matches)) {
            return html_entity_decode(trim($matches[1]));
        }

        return 'Untitled';
    }

    private function extractDescription(string $html): string
    {
        // Try Open Graph
        if (preg_match('/<meta\s+property="og:description"\s+content="([^"]+)"/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }
        
        // Try meta description
        if (preg_match('/<meta\s+name="description"\s+content="([^"]+)"/i', $html, $matches)) {
            return html_entity_decode($matches[1]);
        }

        return '';
    }

    private function extractImage(string $html, string $baseUrl): ?string
    {
        // Try Open Graph image
        if (preg_match('/<meta\s+property="og:image"\s+content="([^"]+)"/i', $html, $matches)) {
            return $this->resolveUrl($matches[1], $baseUrl);
        }

        return null;
    }

    private function extractContent(string $html): string
    {
        // Remove scripts and styles
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Try to find main content
        if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $matches)) {
            $content = $matches[1];
        } else {
            // Fallback to body
            if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
                $content = $matches[1];
            } else {
                $content = $html;
            }
        }

        // Strip tags and clean
        $text = strip_tags($content);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Return first 1000 chars
        return mb_substr($text, 0, 1000);
    }

    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (strpos($url, 'http') === 0) {
            return $url;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        if (strpos($url, '//') === 0) {
            return $scheme . ':' . $url;
        }

        if (strpos($url, '/') === 0) {
            return $scheme . '://' . $host . $url;
        }

        return $scheme . '://' . $host . '/' . $url;
    }

    /**
     * Format scraped content for posting
     */
    public function formatForPost(array $article): string
    {
        $text = "📰 <b>" . htmlspecialchars($article['title']) . "</b>\n\n";
        
        if (!empty($article['description'])) {
            $text .= htmlspecialchars($article['description']) . "\n\n";
        }
        
        $text .= "🔗 <a href=\"" . $article['url'] . "\">Read more</a>";

        return $text;
    }
}
