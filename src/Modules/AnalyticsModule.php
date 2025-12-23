<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\PostService;
use App\Services\ChannelService;
use App\Telegram\Client;
use App\Database\Database;

/**
 * Analytics Module
 * 
 * Provides channel and post analytics
 */
class AnalyticsModule implements PluginInterface
{
    public function register(Container $container): void
    {
        // Services already registered
    }

    public function boot(Container $container): void
    {
        // Booted
    }

    public function getListeners(): array
    {
        return [
            'callback_query' => 'handleCallback',
        ];
    }

    /**
     * Handle callbacks
     */
    public function handleCallback(array $query, array $update, Container $container): void
    {
        $data = $query['data'] ?? '';
        $chatId = $query['message']['chat']['id'] ?? null;
        $messageId = $query['message']['message_id'] ?? null;
        $userId = $query['from']['id'];

        if (!$chatId) return;

        $telegram = $container->make(Client::class);

        // View analytics
        if (strpos($data, 'analytics:') === 0) {
            $channelId = (int)substr($data, 10);
            
            $telegram->answer($query['id']);
            $this->showAnalytics($container, $userId, $chatId, $channelId, $messageId);
            return;
        }

        // Refresh stats
        if (strpos($data, 'refresh_stats:') === 0) {
            $channelId = (int)substr($data, 14);
            
            $telegram->answer($query['id'], "🔄 Refreshing...");
            $this->updateChannelStats($container, $channelId);
            $this->showAnalytics($container, $userId, $chatId, $channelId, $messageId);
            return;
        }
    }

    /**
     * Show analytics
     */
    private function showAnalytics(Container $container, int $userId, int $chatId, int $channelId, ?int $messageId): void
    {
        $channelService = $container->make(ChannelService::class);
        $postService = $container->make(PostService::class);
        $telegram = $container->make(Client::class);
        $db = $container->make(Database::class);

        $channel = $channelService->getChannel($channelId);

        if (!$channel) {
            $telegram->send($chatId, "❌ Channel not found");
            return;
        }

        // Get stats
        $totalPosts = $postService->countChannelPosts($channelId);
        
        // Get recent post count (last 7 days)
        $recentPosts = $db->fetchOne(
            "SELECT COUNT(*) as cnt FROM posts 
             WHERE channel_id = ? 
             AND posted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             AND is_deleted = 0",
            [$channelId]
        );
        $recentCount = (int)($recentPosts['cnt'] ?? 0);

        // Get subscriber count from Telegram if possible
        $subscriberCount = $channel['subscriber_count'] ?? 0;

        // Calculate posting frequency
        $avgPerDay = $totalPosts > 0 ? round($recentCount / 7, 1) : 0;

        $text = "📊 <b>Analytics - " . htmlspecialchars($channel['title']) . "</b>\n\n";
        $text .= "📈 <b>Overview</b>\n";
        $text .= "• Subscribers: " . number_format($subscriberCount) . "\n";
        $text .= "• Total Posts: " . number_format($totalPosts) . "\n";
        $text .= "• Posts (7 days): $recentCount\n";
        $text .= "• Avg per day: $avgPerDay\n\n";

        // Get top performing posts
        $topPosts = $db->fetchAll(
            "SELECT p.*, pa.views, pa.forwards, pa.engagement_rate
             FROM posts p
             LEFT JOIN post_analytics pa ON p.id = pa.post_id
             WHERE p.channel_id = ? AND p.is_deleted = 0
             ORDER BY pa.views DESC
             LIMIT 3",
            [$channelId]
        );

        if (!empty($topPosts)) {
            $text .= "🔥 <b>Top Posts</b>\n";
            foreach ($topPosts as $post) {
                $content = mb_substr($post['content'] ?? 'Media', 0, 30);
                $views = number_format($post['views'] ?? 0);
                $text .= "• $views views: " . htmlspecialchars($content) . "...\n";
            }
            $text .= "\n";
        }

        $text .= "💡 <i>Full analytics coming soon!</i>\n";
        $text .= "Features in development:\n";
        $text .= "• Growth trends\n";
        $text .= "• Engagement metrics\n";
        $text .= "• Best posting times\n";
        $text .= "• Content performance";

        $keyboard = [
            [['text' => '🔄 Refresh Stats', 'callback_data' => 'refresh_stats:' . $channelId]],
            [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]
        ];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Update channel stats from Telegram
     */
    private function updateChannelStats(Container $container, int $channelId): void
    {
        $telegram = $container->make(Client::class);
        $channelService = $container->make(ChannelService::class);

        try {
            // Get chat info
            $chat = $telegram->getChat($channelId);
            
            if ($chat) {
                // Get member count if available
                $memberCount = $telegram->getChatMemberCount($channelId);
                
                if ($memberCount) {
                    $db = $container->make(Database::class);
                    $db->execute(
                        "UPDATE channels SET subscriber_count = ? WHERE channel_id = ?",
                        [$memberCount, $channelId]
                    );
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to update channel stats: " . $e->getMessage());
        }
    }
}
