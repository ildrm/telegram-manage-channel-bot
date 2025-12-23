<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * RSS Service
 * 
 * Manages RSS feed ingestion and auto-posting
 */
class RSSService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Add RSS feed
     */
    public function addFeed(int $channelId, int $userId, string $feedUrl, ?string $template = null): int
    {
        return $this->db->insert(
            "INSERT INTO rss_feeds (channel_id, user_id, feed_url, template, active)
             VALUES (?, ?, ?, ?, 1)",
            [$channelId, $userId, $feedUrl, $template]
        );
    }

    /**
     * Get feed
     */
    public function getFeed(int $feedId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM rss_feeds WHERE id = ?",
            [$feedId]
        );
    }

    /**
     * Get channel feeds
     */
    public function getChannelFeeds(int $channelId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM rss_feeds WHERE channel_id = ? ORDER BY created_at DESC",
            [$channelId]
        );
    }

    /**
     * Get active feeds for processing
     */
    public function getActiveFeeds(int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM rss_feeds 
             WHERE active = 1 
             AND (last_check IS NULL OR last_check < DATE_SUB(NOW(), INTERVAL 15 MINUTE))
             ORDER BY last_check ASC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Update feed last check
     */
    public function updateLastCheck(int $feedId, ?string $lastItemId = null): void
    {
        $this->db->execute(
            "UPDATE rss_feeds SET last_check = CURRENT_TIMESTAMP, last_item_id = ? WHERE id = ?",
            [$lastItemId, $feedId]
        );
    }

    /**
     * Toggle feed active status
     */
    public function toggleFeed(int $feedId): void
    {
        $this->db->execute(
            "UPDATE rss_feeds SET active = NOT active WHERE id = ?",
            [$feedId]
        );
    }

    /**
     * Delete feed
     */
    public function deleteFeed(int $feedId): void
    {
        $this->db->execute("DELETE FROM rss_feeds WHERE id = ?", [$feedId]);
    }

    /**
     * Fetch RSS feed items
     */
    public function fetchFeedItems(string $feedUrl): array
    {
        try {
            $xml = @file_get_contents($feedUrl);
            
            if (!$xml) {
                return [];
            }

            $feed = @simplexml_load_string($xml);
            
            if (!$feed) {
                return [];
            }

            $items = [];

            // RSS 2.0
            if (isset($feed->channel->item)) {
                foreach ($feed->channel->item as $item) {
                    $items[] = [
                        'title' => (string)$item->title,
                        'link' => (string)$item->link,
                        'description' => (string)$item->description,
                        'pubDate' => (string)$item->pubDate,
                        'guid' => (string)$item->guid ?: (string)$item->link
                    ];
                }
            }
            // Atom
            elseif (isset($feed->entry)) {
                foreach ($feed->entry as $entry) {
                    $items[] = [
                        'title' => (string)$entry->title,
                        'link' => (string)$entry->link['href'],
                        'description' => (string)$entry->summary,
                        'pubDate' => (string)$entry->updated,
                        'guid' => (string)$entry->id ?: (string)$entry->link['href']
                    ];
                }
            }

            return $items;
        } catch (\Exception $e) {
            error_log("Failed to fetch RSS feed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Format feed item using template
     */
    public function formatItem(array $item, ?string $template = null): string
    {
        if (!$template) {
            $template = "<b>{{title}}</b>\n\n{{description}}\n\n🔗 {{link}}";
        }

        $replacements = [
            '{{title}}' => $item['title'] ?? '',
            '{{link}}' => $item['link'] ?? '',
            '{{description}}' => $item['description'] ?? '',
            '{{date}}' => $item['pubDate'] ?? ''
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
