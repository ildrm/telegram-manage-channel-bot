<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Queue Management Service
 * 
 * Balance posting queue across channels
 */
class QueueManagementService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Balance queue across channels
     */
    public function balanceQueue(int $userId): array
    {
        // Get user's channels
        $channels = $this->db->fetchAll(
            "SELECT c.channel_id, COUNT(s.id) as queued_count
             FROM channels c
             JOIN channel_owners co ON c.channel_id = co.channel_id
             LEFT JOIN scheduled s ON c.channel_id = s.channel_id AND s.status = 'pending'
             WHERE co.user_id = ?
             GROUP BY c.channel_id",
            [$userId]
        );

        if (empty($channels)) {
            return [];
        }

        // Calculate average queue size
        $totalQueued = array_sum(array_column($channels, 'queued_count'));
        $avgQueueSize = $totalQueued / count($channels);

        $rebalanced = [];

        foreach ($channels as $channel) {
            $channelId = $channel['channel_id'];
            $currentQueue = (int)$channel['queued_count'];
            
            if ($currentQueue < $avgQueueSize * 0.7) {
                // Channel needs more scheduled posts
                $rebalanced[$channelId] = [
                    'status' => 'needs_content',
                    'current' => $currentQueue,
                    'target' => ceil($avgQueueSize)
                ];
            } elseif ($currentQueue > $avgQueueSize * 1.3) {
                // Channel has too many scheduled posts
                $rebalanced[$channelId] = [
                    'status' => 'overloaded',
                    'current' => $currentQueue,
                    'target' => floor($avgQueueSize)
                ];
            } else {
                $rebalanced[$channelId] = [
                    'status' => 'balanced',
                    'current' => $currentQueue,
                    'target' => ceil($avgQueueSize)
                ];
            }
        }

        return $rebalanced;
    }

    /**
     * Auto-distribute posts across channels
     */
    public function distributePosts(array $channelIds, array $posts): array
    {
        $distribution = [];
        $postCount = count($posts);
        $channelCount = count($channelIds);
        
        if ($channelCount === 0 || $postCount === 0) {
            return [];
        }

        $postsPerChannel = floor($postCount / $channelCount);
        $remainder = $postCount % $channelCount;

        $postIndex = 0;

        foreach ($channelIds as $i => $channelId) {
            $allocation = $postsPerChannel + ($i < $remainder ? 1 : 0);
            
            $distribution[$channelId] = array_slice($posts, $postIndex, $allocation);
            $postIndex += $allocation;
        }

        return $distribution;
    }

    /**
     * Optimize posting times
     */
    public function optimizePostingTimes(int $channelId, array $scheduledPosts): array
    {
        // Get best performing hours
        $bestHours = $this->db->fetchAll(
            "SELECT HOUR(posted_at) as hour, AVG(pa.engagement_rate) as avg_engagement
             FROM posts p
             JOIN post_analytics pa ON p.id = pa.post_id
             WHERE p.channel_id = ?
             GROUP BY HOUR(posted_at)
             ORDER BY avg_engagement DESC
             LIMIT 5",
            [$channelId]
        );

        $optimizedHours = array_column($bestHours, 'hour');
        
        if (empty($optimizedHours)) {
            // Default optimal hours
            $optimizedHours = [9, 12, 15, 18, 21];
        }

        $optimized = [];
        $hourIndex = 0;

        foreach ($scheduledPosts as $post) {
            $baseTime = strtotime($post['schedule_time']);
            $date = date('Y-m-d', $baseTime);
            $hour = $optimizedHours[$hourIndex % count($optimizedHours)];
            
            $optimized[] = [
                'post_id' => $post['id'],
                'original_time' => $post['schedule_time'],
                'optimized_time' => "$date $hour:00:00",
                'reason' => "Moved to high-engagement hour ($hour:00)"
            ];

            $hourIndex++;
        }

        return $optimized;
    }
}
