<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Health Monitoring Service
 * 
 * Monitor channel health and performance
 */
class HealthMonitoringService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get channel health score
     */
    public function getHealthScore(int $channelId): array
    {
        $metrics = [];
        
        // 1. Posting frequency (0-25 points)
        $metrics['posting_frequency'] = $this->checkPostingFrequency($channelId);
        
        // 2. Engagement rate (0-30 points)
        $metrics['engagement'] = $this->checkEngagement($channelId);
        
        // 3. Growth rate (0-25 points)
        $metrics['growth'] = $this->checkGrowth($channelId);
        
        // 4. Content quality (0-20 points)
        $metrics['quality'] = $this->checkQuality($channelId);
        
        $totalScore = array_sum($metrics);
        
        return [
            'score' => $totalScore,
            'grade' => $this->getGrade($totalScore),
            'metrics' => $metrics,
            'status' => $this->getStatus($totalScore),
            'recommendations' => $this->getRecommendations($metrics)
        ];
    }

    private function checkPostingFrequency(int $channelId): int
    {
        $count = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM posts 
             WHERE channel_id = ? 
             AND posted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             AND is_deleted = 0",
            [$channelId]
        );

        $postsPerWeek = (int)($count['cnt'] ?? 0);
        
        // Ideal: 7-14 posts/week
        if ($postsPerWeek >= 7 && $postsPerWeek <= 14) return 25;
        if ($postsPerWeek >= 4) return 20;
        if ($postsPerWeek >= 2) return 15;
        if ($postsPerWeek >= 1) return 10;
        return 0;
    }

    private function checkEngagement(int $channelId): int
    {
        $avgEngagement = $this->db->fetchOne(
            "SELECT AVG(pa.engagement_rate) as avg_rate
             FROM posts p
             JOIN post_analytics pa ON p.id = pa.post_id
             WHERE p.channel_id = ?
             AND p.posted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$channelId]
        );

        $rate = (float)($avgEngagement['avg_rate'] ?? 0);
        
        if ($rate >= 10) return 30;
        if ($rate >= 5) return 25;
        if ($rate >= 2) return 20;
        if ($rate >= 1) return 15;
        return 10;
    }

    private function checkGrowth(int $channelId): int
    {
        $growth = $this->db->fetchOne(
            "SELECT 
                (SELECT subscriber_count FROM channel_analytics WHERE channel_id = ? ORDER BY date DESC LIMIT 1) -
                (SELECT subscriber_count FROM channel_analytics WHERE channel_id = ? ORDER BY date DESC LIMIT 1 OFFSET 30) as growth
                ", 
            [$channelId, $channelId]
        );

        $growthCount = (int)($growth['growth'] ?? 0);
        
        if ($growthCount > 100) return 25;
        if ($growthCount > 50) return 20;
        if ($growthCount > 20) return 15;
        if ($growthCount > 0) return 10;
        return 5;
    }

    private function checkQuality(int $channelId): int
    {
        // Check for complete posts, media usage, etc.
        $stats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN media_id IS NOT NULL THEN 1 ELSE 0 END) as with_media,
                SUM(CASE WHEN LENGTH(content) > 100 THEN 1 ELSE 0 END) as detailed
             FROM posts
             WHERE channel_id = ?
             AND posted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$channelId]
        );

        $mediaPercentage = ($stats['total'] > 0) ? ($stats['with_media'] / $stats['total']) * 100 : 0;
        $detailedPercentage = ($stats['total'] > 0) ? ($stats['detailed'] / $stats['total']) * 100 : 0;
        
        $score = 0;
        $score += ($mediaPercentage > 50) ? 10 : 5;
        $score += ($detailedPercentage > 70) ? 10 : 5;
        
        return $score;
    }

    private function getGrade(int $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }

    private function getStatus(int $score): string
    {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }

    private function getRecommendations(array $metrics): array
    {
        $recommendations = [];
        
        if ($metrics['posting_frequency'] < 15) {
            $recommendations[] = 'Increase posting frequency to 1-2 posts per day';
        }
        
        if ($metrics['engagement'] < 20) {
            $recommendations[] = 'Improve content engagement with polls, questions, and media';
        }
        
        if ($metrics['growth'] < 15) {
            $recommendations[] = 'Promote channel to grow subscriber base';
        }
        
        if ($metrics['quality'] < 15) {
            $recommendations[] = 'Add more media and detailed content to posts';
        }
        
        return $recommendations;
    }

    /**
     * Get cross-channel analytics
     */
    public function getCrossChannelAnalytics(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT 
                c.channel_id,
                c.title,
                COUNT(DISTINCT p.id) as total_posts,
                AVG(pa.engagement_rate) as avg_engagement,
                SUM(pa.views) as total_views
             FROM channels c
             JOIN channel_owners co ON c.channel_id = co.channel_id
             LEFT JOIN posts p ON c.channel_id = p.channel_id
             LEFT JOIN post_analytics pa ON p.id = pa.post_id
             WHERE co.user_id = ?
             GROUP BY c.channel_id
             ORDER BY total_views DESC",
            [$userId]
        );
    }
}
