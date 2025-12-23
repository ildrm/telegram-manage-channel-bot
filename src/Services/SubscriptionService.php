<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Subscription Service
 * 
 * Handles user subscriptions and quotas for monetization
 */
class SubscriptionService
{
    private Database $db;

    // Plan limits
    private const PLANS = [
        'free' => [
            'channels' => 2,
            'posts_per_month' => 100,
            'scheduled_posts' => 10,
            'rss_feeds' => 2,
            'team_members' => 1
        ],
        'pro' => [
            'channels' => 10,
            'posts_per_month' => 1000,
            'scheduled_posts' => 100,
            'rss_feeds' => 10,
            'team_members' => 5
        ],
        'business' => [
            'channels' => -1, // unlimited
            'posts_per_month' => -1,
            'scheduled_posts' => -1,
            'rss_feeds' => -1,
            'team_members' => -1
        ]
    ];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get user's plan
     */
    public function getUserPlan(int $userId): string
    {
        $user = $this->db->fetchOne(
            "SELECT subscription_plan FROM users WHERE telegram_id = ?",
            [$userId]
        );

        return $user['subscription_plan'] ?? 'free';
    }

    /**
     * Check if user can create channel
     */
    public function canCreateChannel(int $userId): bool
    {
        $plan = $this->getUserPlan($userId);
        $limit = self::PLANS[$plan]['channels'];

        if ($limit === -1) {
            return true; // unlimited
        }

        $count = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM channel_owners WHERE user_id = ?",
            [$userId]
        );

        return ($count['cnt'] ?? 0) < $limit;
    }

    /**
     * Check quota
     */
    public function checkQuota(int $userId, string $feature): bool
    {
        $plan = $this->getUserPlan($userId);
        $limits = self::PLANS[$plan];

        if (!isset($limits[$feature])) {
            return true;
        }

        $limit = $limits[$feature];
        
        if ($limit === -1) {
            return true; // unlimited
        }

        // Check current usage
        $usage = $this->getUsage($userId, $feature);

        return $usage < $limit;
    }

    /**
     * Get feature usage
     */
    private function getUsage(int $userId, string $feature): int
    {
        switch ($feature) {
            case 'channels':
                $result = $this->db->fetchOne(
                    "SELECT COUNT(*) as cnt FROM channel_owners WHERE user_id = ?",
                    [$userId]
                );
                return (int)($result['cnt'] ?? 0);

            case 'posts_per_month':
                $result = $this->db->fetchOne(
                    "SELECT COUNT(*) as cnt FROM posts p 
                     JOIN channel_owners co ON p.channel_id = co.channel_id
                     WHERE co.user_id = ? 
                     AND p.posted_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
                    [$userId]
                );
                return (int)($result['cnt'] ?? 0);

            case 'scheduled_posts':
                $result = $this->db->fetchOne(
                    "SELECT COUNT(*) as cnt FROM scheduled s
                     JOIN channel_owners co ON s.channel_id = co.channel_id
                     WHERE co.user_id = ? AND s.status = 'pending'",
                    [$userId]
                );
                return (int)($result['cnt'] ?? 0);

            default:
                return 0;
        }
    }

    /**
     * Upgrade plan
     */
    public function upgradePlan(int $userId, string $plan): bool
    {
        if (!isset(self::PLANS[$plan])) {
            return false;
        }

        $this->db->execute(
            "UPDATE users SET subscription_plan = ? WHERE telegram_id = ?",
            [$plan, $userId]
        );

        return true;
    }

    /**
     * Get usage stats
     */
    public function getUsageStats(int $userId): array
    {
        $plan = $this->getUserPlan($userId);
        $limits = self::PLANS[$plan];

        $stats = [
            'plan' => $plan,
            'limits' => $limits,
            'usage' => []
        ];

        foreach ($limits as $feature => $limit) {
            $stats['usage'][$feature] = [
                'used' => $this->getUsage($userId, $feature),
                'limit' => $limit,
                'percentage' => $limit > 0 ? round(($this->getUsage($userId, $feature) / $limit) * 100, 2) : 0
            ];
        }

        return $stats;
    }
}
