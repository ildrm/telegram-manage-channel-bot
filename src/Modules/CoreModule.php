<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\UserService;
use App\Services\ChannelService;
use App\Services\AuthorizationService;
use App\Telegram\Client;

/**
 * Core Module
 * 
 * Handles basic bot commands and my_chat_member events
 */
class CoreModule implements PluginInterface
{
    public function register(Container $container): void
    {
        // Services are already registered
    }

    public function boot(Container $container): void
    {
        // Module booted
    }

    public function getListeners(): array
    {
        return [
            'my_chat_member' => 'handleMyChatMember',
            'command' => 'handleCommand'
        ];
    }

    /**
     * Handle bot added/removed from channel
     */
    public function handleMyChatMember(array $update, array $fullUpdate, Container $container): void
    {
        $chatMember = $update;
        $chat = $chatMember['chat'];
        $from = $chatMember['from'];
        $newStatus = $chatMember['new_chat_member']['status'];

        // Only handle channels
        if (!in_array($chat['type'], ['channel', 'supergroup'])) {
            return;
        }

        $channelService = $container->make(ChannelService::class);
        $userService = $container->make(UserService::class);
        $telegram = $container->make(Client::class);

        $channelId = $chat['id'];
        $userId = $from['id'];

        // Ensure user exists
        $userService->getOrCreateUser($from);

        // Bot was promoted to admin
        if (in_array($newStatus, ['administrator', 'creator'])) {
            // Create or update channel
            $channelService->getOrCreateChannel($channelId, $chat);

            // Add user as owner
            $channelService->addOwner($channelId, $userId, $newStatus === 'creator');

            // Notify user
            $telegram->send(
                $userId,
                "✅ <b>Channel Added!</b>\n\n" .
                "You can now manage <b>" . htmlspecialchars($chat['title'] ?? 'your channel') . "</b>\n\n" .
                "Use /start to open the dashboard."
            );
        }

        // Bot was removed
        if (in_array($newStatus, ['left', 'kicked'])) {
            $channelService->deactivateChannel($channelId);

            $telegram->send(
                $userId,
                "❌ <b>Channel Removed</b>\n\n" .
                "The bot was removed from <b>" . htmlspecialchars($chat['title'] ?? 'the channel') . "</b>"
            );
        }
    }

    /**
     * Handle commands
     */
    public function handleCommand(array $message, array $update, Container $container): void
    {
        $text = $message['text'] ?? '';
        $userId = $message['from']['id'];
        $chatId = $message['chat']['id'];

        // Only handle private chats
        if ($message['chat']['type'] !== 'private') {
            return;
        }

        $userService = $container->make(UserService::class);
        $telegram = $container->make(Client::class);

        // Ensure user exists
        $userService->getOrCreateUser($message['from']);

        $parts = explode(' ', $text, 2);
        $command = strtolower($parts[0]);

        switch ($command) {
            case '/start':
                $userService->clearSession($userId);
                $this->showDashboard($container, $userId, $chatId);
                break;

            case '/help':
                $this->showHelp($container, $chatId);
                break;

            case '/cancel':
                $userService->clearSession($userId);
                $telegram->send(
                    $chatId,
                    "✅ Operation cancelled.",
                    [[['text' => '« Back to Menu', 'callback_data' => 'menu']]]
                );
                break;

            default:
                $telegram->send($chatId, "Unknown command. Use /start to begin.");
                break;
        }
    }

    /**
     * Show main dashboard
     */
    private function showDashboard(Container $container, int $userId, int $chatId): void
    {
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channels = $channelService->getUserChannels($userId, 0, 5);
        $total = $channelService->countUserChannels($userId);

        $text = "🎛 <b>Channel Management Dashboard</b>\n\n";

        if (empty($channels)) {
            $text .= "You don't have any channels yet.\n\n";
            $text .= "➕ Add this bot as administrator to your channel to get started!\n\n";
            $text .= "💡 <i>When you add the bot, you'll automatically become the owner.</i>";

            $keyboard = [
                [['text' => '📖 Help', 'callback_data' => 'help']],
                [['text' => '🔄 Refresh', 'callback_data' => 'menu']]
            ];
        } else {
            $text .= "📊 You manage <b>$total</b> channel" . ($total !== 1 ? 's' : '') . "\n\n";
            $text .= "Select a channel to manage:";

            $keyboard = [];
            foreach ($channels as $ch) {
                $emoji = $ch['type'] === 'channel' ? '📢' : '👥';
                $keyboard[] = [['text' => "$emoji " . $ch['title'], 'callback_data' => 'ch:' . $ch['channel_id']]];
            }

            if ($total > 5) {
                $keyboard[] = [['text' => '📋 View All Channels', 'callback_data' => 'channels:0']];
            }

            $keyboard[] = [
                ['text' => '📖 Help', 'callback_data' => 'help'],
                ['text' => '🔄 Refresh', 'callback_data' => 'menu']
            ];
        }

        $telegram->send($chatId, $text, $keyboard);
    }

    /**
     * Show help
     */
    private function showHelp(Container $container, int $chatId): void
    {
        $telegram = $container->make(Client::class);

        $text = "📖 <b>Channel Management Bot - Help</b>\n\n" .
                "<b>Getting Started:</b>\n" .
                "1. Add this bot as administrator to your channel\n" .
                "2. You'll automatically become the owner\n" .
                "3. Use /start to open the dashboard\n\n" .
                "<b>Features:</b>\n" .
                "✍️ Post text, photos, videos, documents, polls\n" .
                "⏰ Schedule posts (one-time or recurring)\n" .
                "📝 Save drafts for later\n" .
                "📊 View analytics and insights\n" .
                "🔧 Manage channel settings\n" .
                "📡 Auto-post from RSS feeds\n" .
                "💾 Backup and restore posts\n" .
                "🎨 Customize with watermarks & signatures\n" .
                "👥 Manage team with roles & permissions\n" .
                "🎯 Create campaigns with A/B testing\n\n" .
                "<b>Commands:</b>\n" .
                "/start - Open dashboard\n" .
                "/help - Show this help\n" .
                "/cancel - Cancel current operation\n\n" .
                "💡 <i>All your channels are private - only you can see and manage them!</i>";

        $telegram->send($chatId, $text, [[['text' => '« Back to Menu', 'callback_data' => 'menu']]]);
    }
}
