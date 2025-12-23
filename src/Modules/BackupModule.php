<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\BackupService;
use App\Services\ChannelService;
use App\Telegram\Client;

/**
 * Backup Module
 * 
 * Handles data backup and export
 */
class BackupModule implements PluginInterface
{
    public function register(Container $container): void
    {
        $container->singleton(BackupService::class);
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

        // View backups
        if (strpos($data, 'backup:') === 0) {
            $channelId = (int)substr($data, 7);
            $telegram->answer($query['id']);
            $this->showBackups($container, $userId, $chatId, $channelId, $messageId);
            return;
        }

        // Create backup
        if (strpos($data, 'create_backup:') === 0) {
            $channelId = (int)substr($data, 14);
            $telegram->answer($query['id'], "🔄 Creating backup...");
            $this->createBackup($container, $userId, $chatId, $channelId, $messageId);
            return;
        }
    }

    /**
     * Show backups
     */
    private function showBackups(Container $container, int $userId, int $chatId, int $channelId, ?int $messageId): void
    {
        $backupService = $container->make(BackupService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($channelId);
        $backups = $backupService->getChannelBackups($channelId, 0, 5);

        $text = "💾 <b>Backups - " . htmlspecialchars($channel['title']) . "</b>\n\n";

        if (empty($backups)) {
            $text .= "No backups yet.\n\n";
            $text .= "💡 Create regular backups to protect your data!";
        } else {
            $text .= "Recent backups:\n\n";

            foreach ($backups as $backup) {
                $date = date('M d, Y H:i', strtotime($backup['created_at']));
                $posts = $backup['post_count'];
                $text .= "📦 $date\n";
                $text .= "  $posts posts backed up\n\n";
            }
        }

        $keyboard = [
            [['text' => '➕ Create Backup', 'callback_data' => 'create_backup:' . $channelId]],
            [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]
        ];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Create backup
     */
    private function createBackup(Container $container, int $userId, int $chatId, int $channelId, ?int $messageId): void
    {
        $backupService = $container->make(BackupService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($channelId);

        try {
            $backupId = $backupService->createBackup($channelId, $userId);

            $text = "✅ <b>Backup Created!</b>\n\n" .
                    "Channel: <b>" . htmlspecialchars($channel['title']) . "</b>\n\n" .
                    "Your data has been backed up successfully.\n\n" .
                    "💡 Backups include all posts, settings, and channel data.";

            $keyboard = [[['text' => '« Back to Backups', 'callback_data' => 'backup:' . $channelId]]];

            if ($messageId) {
                $telegram->edit($chatId, $messageId, $text, $keyboard);
            } else {
                $telegram->send($chatId, $text, $keyboard);
            }
        } catch (\Exception $e) {
            error_log("Failed to create backup: " . $e->getMessage());
            $telegram->send($chatId, "❌ Error creating backup: " . $e->getMessage());
        }
    }
}
