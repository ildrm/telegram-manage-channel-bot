<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\MultiChannelService;
use App\Services\ChannelService;
use App\Services\UserService;
use App\Telegram\Client;

/**
 * Multi-Channel Module
 * 
 * Handles cross-posting and channel groups
 */
class MultiChannelModule implements PluginInterface
{
    public function register(Container $container): void
    {
        $container->singleton(MultiChannelService::class);
    }

    public function boot(Container $container): void
    {
        // Booted
    }

    public function getListeners(): array
    {
        return [
            'callback_query' => 'handleCallback',
            'text' => 'handleText'
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

        // Cross-post menu
        if (strpos($data, 'crosspost:') === 0) {
            $channelId = (int)substr($data, 10);
            $telegram->answer($query['id']);
            $this->showCrossPostMenu($container, $userId, $chatId, $channelId, $messageId);
            return;
        }
    }

    /**
     * Handle text
     */
    public function handleText(array $message, array $update, Container $container): void
    {
        // Implementation for text handling if needed
    }

    /**
     * Show cross-post menu
     */
    private function showCrossPostMenu(Container $container, int $userId, int $chatId, int $channelId, ?int $messageId): void
    {
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $allChannels = $channelService->getUserChannels($userId, 0, 20);
        $currentChannel = $channelService->getChannel($channelId);

        $text = "📤 <b>Cross-Post</b>\n\n";
        $text .= "Select additional channels to post to:\n\n";
        $text .= "Original: <b>" . htmlspecialchars($currentChannel['title']) . "</b>";

        $keyboard = [];

        foreach ($allChannels as $ch) {
            if ($ch['channel_id'] != $channelId) {
                $emoji = $ch['type'] === 'channel' ? '📢' : '👥';
                $keyboard[] = [[
                    'text' => "$emoji " . $ch['title'],
                    'callback_data' => 'select_crosspost:' . $channelId . ':' . $ch['channel_id']
                ]];
            }
        }

        $keyboard[] = [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }
}
