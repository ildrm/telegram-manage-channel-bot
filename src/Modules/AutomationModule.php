<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\AutomationService;
use App\Telegram\Client;

/**
 * Automation Module
 * 
 * Handles evergreen reposting and advanced automation
 */
class AutomationModule implements PluginInterface
{
    public function register(Container $container): void
    {
        $container->singleton(AutomationService::class);
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

        // Automation menu
        if (strpos($data, 'automation:') === 0) {
            $channelId = (int)substr($data, 11);
            $telegram->answer($query['id']);
            $this->showAutomationMenu($container, $userId, $chatId, $channelId, $messageId);
            return;
        }
    }

    /**
     * Show automation menu
     */
    private function showAutomationMenu(Container $container, int $userId, int $chatId, int $channelId, ?int $messageId): void
    {
        $telegram = $container->make(Client::class);

        $text = "🤖 <b>Automation</b>\n\n";
        $text .= "Configure automation features:";

        $keyboard = [
            [['text' => '♻️ Evergreen Reposting', 'callback_data' => 'evergreen:' . $channelId]],
            [['text' => '📡 RSS Auto-Post', 'callback_data' => 'rss:' . $channelId]],
            [['text' => '⏰ Auto-Schedule', 'callback_data' => 'auto_schedule:' . $channelId]],
            [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]
        ];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

 /**
     * Process evergreen reposting (called by cron)
     */
    public static function processEvergreen(Container $container): int
    {
        $db = $container->make(\App\Database\Database::class);
        $telegram = $container->make(Client::class);
        $postService = $container->make(\App\Services\PostService::class);

        // Get channels with evergreen enabled
        $channels = $db->fetchAll(
            "SELECT cs.channel_id, cs.value as interval_days
             FROM channel_settings cs
             WHERE cs.setting_key = 'evergreen_repost' AND cs.value > 0"
        );

        $reposted = 0;

        foreach ($channels as $channel) {
            $channelId = $channel['channel_id'];
            $intervalDays = (int)$channel['interval_days'];

            // Find top post that hasn't been reposted recently
            $topPost = $db->fetchOne(
                "SELECT p.* 
                 FROM posts p
                 LEFT JOIN post_analytics pa ON p.id = pa.post_id
                 WHERE p.channel_id = ? 
                 AND p.is_deleted = 0
                 AND p.posted_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                 AND (p.last_reposted_at IS NULL OR p.last_reposted_at < DATE_SUB(NOW(), INTERVAL ? DAY))
                 ORDER BY pa.engagement_rate DESC
                 LIMIT 1",
                [$channelId, $intervalDays, $intervalDays]
            );

            if ($topPost) {
                try {
                    // Repost
                    $params = ['chat_id' => $channelId];

                    switch ($topPost['content_type']) {
                        case 'photo':
                            $params['photo'] = $topPost['media_id'];
                            $params['caption'] = $topPost['content'];
                            $result = $telegram->sendPhoto($params);
                            break;

                        default:
                            $params['text'] = $topPost['content'];
                            $result = $telegram->sendMessage($params);
                            break;
                    }

                    if ($result) {
                        // Mark as reposted
                        $db->execute(
                            "UPDATE posts SET last_reposted_at = NOW() WHERE id = ?",
                            [$topPost['id']]
                        );

                        // Create new post record
                        $postService->createPost($channelId, $result['message_id'], $topPost['user_id'], [
                            'content_type' => $topPost['content_type'],
                            'content' => $topPost['content'],
                            'media_id' => $topPost['media_id']
                        ]);

                        $reposted++;
                    }
                } catch (\Exception $e) {
                    error_log("Evergreen repost failed: " . $e->getMessage());
                }
            }
        }

        return $reposted;
    }
}
