<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Analytics Service
 * 
 * Advanced analytics and reporting
 */
class AnalyticsService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get growth trend
     */
    public function getGrowthTrend(int $channelId, int $days = 30): array
    {
        return $this->db->fetchAll(
            "SELECT DATE(date) as date, subscriber_count, new_subscribers, unsubscribers
             FROM channel_analytics
             WHERE channel_id = ? AND date >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY date ASC",
            [$channelId, $days]
        );
    }

    /**
     * Get best posting times
     */
    public function getBestPostingTimes(int $channelId): array
    {
        return $this->db->fetchAll(
            "SELECT HOUR(posted_at) as hour, AVG(pa.engagement_rate) as avg_engagement
             FROM posts p
             JOIN post_analytics pa ON p.id = pa.post_id
             WHERE p.channel_id = ? AND p.is_deleted = 0
             GROUP BY HOUR(posted_at)
             ORDER BY avg_engagement DESC
             LIMIT 5",
            [$channelId]
        );
    }

    /**
     * Get content performance by type
     */
    public function getContentPerformance(int $channelId): array
    {
        return $this->db->fetchAll(
            "SELECT p.content_type, 
                    COUNT(*) as count,
                    AVG(pa.views) as avg_views,
                    AVG(pa.forwards) as avg_forwards,
                    AVG(pa.engagement_rate) as avg_engagement
             FROM posts p
             LEFT JOIN post_analytics pa ON p.id = pa.post_id
             WHERE p.channel_id = ? AND p.is_deleted = 0
             GROUP BY p.content_type",
            [$channelId]
        );
    }

    /**
     * Detect posting inactivity
     */
    public function detectInactivity(int $channelId, int $daysThreshold = 7): bool
    {
        $lastPost = $this->db->fetchOne(
            "SELECT MAX(posted_at) as last_post FROM posts 
             WHERE channel_id = ? AND is_deleted = 0",
            [$channelId]
        );

        if (!$lastPost || !$lastPost['last_post']) {
            return true;
        }

        $daysSinceLastPost = (time() - strtotime($lastPost['last_post'])) / 86400;
        return $daysSinceLastPost > $daysThreshold;
    }

    /**
     * Export analytics report
     */
    public function exportReport(int $channelId, string $format = 'json'): string
    {
        $data = [
            'channel_id' => $channelId,
            'generated_at' => date('Y-m-d H:i:s'),
            'growth_trend' => $this->getGrowthTrend($channelId, 30),
            'best_posting_times' => $this->getBestPostingTimes($channelId),
            'content_performance' => $this->getContentPerformance($channelId)
        ];

        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT);
        }

        // CSV format
        $csv = "Analytics Report\n";
        $csv .= "Generated: " . $data['generated_at'] . "\n\n";
        
        $csv .= "Content Performance\n";
        $csv .= "Type,Count,Avg Views,Avg Forwards,Avg Engagement\n";
        foreach ($data['content_performance'] as $perf) {
            $csv .= implode(',', [
                $perf['content_type'],
                $perf['count'],
                round($perf['avg_views'] ?? 0, 2),
                round($perf['avg_forwards'] ?? 0, 2),
                round($perf['avg_engagement'] ?? 0, 2)
            ]) . "\n";
        }

        return $csv;
    }
}
