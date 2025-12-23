<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\ChannelService;
use App\Telegram\Client;

/**
 * Channel Module
 * 
 * Handles channel listing and management
 */
class ChannelModule implements PluginInterface
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

        // View all channels with pagination
        if (strpos($data, 'channels:') === 0) {
            $offset = (int)substr($data, 9);
            
            $telegram->answer($query['id']);
            $this->showAllChannels($container, $userId, $chatId, $offset, $messageId);
            return;
        }
    }

    /**
     * Show all channels with pagination
     */
    private function showAllChannels(Container $container, int $userId, int $chatId, int $offset, ?int $messageId): void
    {
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channels = $channelService->getUserChannels($userId, $offset, 10);
        $total = $channelService->countUserChannels($userId);

        $text = "📋 <b>All Your Channels</b>\n\n";
        $text .= "Total: $total channel" . ($total !== 1 ? 's' : '') . "\n\n";

        if (empty($channels)) {
            $text .= "No channels found.";
            $keyboard = [[['text' => '« Back to Menu', 'callback_data' => 'menu']]];
        } else {
            $text .= "Showing " . ($offset + 1) . "-" . min($offset + count($channels), $total) . " of $total:";

            $keyboard = [];
            foreach ($channels as $ch) {
                $emoji = $ch['type'] === 'channel' ? '📢' : '👥';
                $keyboard[] = [[
                    'text' => "$emoji " . $ch['title'],
                    'callback_data' => 'ch:' . $ch['channel_id']
                ]];
            }

            // Pagination
            $navRow = [];
            if ($offset > 0) {
                $navRow[] = ['text' => '⬅️ Previous', 'callback_data' => 'channels:' . ($offset - 10)];
            }
            if ($offset + 10 < $total) {
                $navRow[] = ['text' => 'Next ➡️', 'callback_data' => 'channels:' . ($offset + 10)];
            }

            if (!empty($navRow)) {
                $keyboard[] = $navRow;
            }

            $keyboard[] = [['text' => '« Back to Menu', 'callback_data' => 'menu']];
        }

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }
}
