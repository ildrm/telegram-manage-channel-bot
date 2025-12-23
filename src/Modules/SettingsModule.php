<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\ChannelService;
use App\Services\AuthorizationService;
use App\Telegram\Client;

/**
 * Settings Module
 * 
 * Manages channel settings and configuration
 */
class SettingsModule implements PluginInterface
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

        // View settings
        if (strpos($data, 'settings:') === 0) {
            $channelId = (int)substr($data, 9);
            
            $authService = $container->make(AuthorizationService::class);
            if (!$authService->userHasPermission($userId, $channelId, 'settings.manage')) {
                $telegram->answer($query['id'], "❌ No permission", true);
                return;
            }

            $telegram->answer($query['id']);
            $this->showSettings($container, $userId, $chatId, $channelId, $messageId);
            return;
        }

        // Toggle setting
        if (strpos($data, 'toggle:') === 0) {
            $parts = explode(':', $data);
            $channelId = (int)$parts[1];
            $setting = $parts[2];

            $telegram->answer($query['id']);
            $this->toggleSetting($container, $userId, $chatId, $channelId, $setting, $messageId);
            return;
        }
    }

    /**
     * Show settings
     */
    private function showSettings(Container $container, int $userId, int $chatId, int $channelId, ?int $messageId): void
    {
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($channelId);
        $settings = $channelService->getSettings($channelId);

        $text = "🔧 <b>Settings - " . htmlspecialchars($channel['title']) . "</b>\n\n";
        $text .= "<b>Post Settings</b>\n";
        $text .= $this->formatToggle('Auto-pin new posts', $settings['auto_pin_new_posts']);
        $text .= $this->formatToggle('Require approval', $settings['post_approval_required']);
        $text .= "\n";

        $text .= "<b>Interaction Settings</b>\n";
        $text .= $this->formatToggle('Comments enabled', $settings['comments_enabled']);
        $text .= $this->formatToggle('Reactions enabled', $settings['reactions_enabled']);
        $text .= "\n";

        $text .= "<b>Advanced</b>\n";
        $text .= "• Timezone: " . $settings['default_timezone'] . "\n";
        $text .= "• Signature: " . ($settings['signature'] ? 'Set' : 'None') . "\n";
        $text .= "• Watermark: " . ($settings['watermark'] ? 'Set' : 'None') . "\n\n";

        $text .= "💡 <i>More settings coming soon!</i>";

        $keyboard = [
            [['text' => ($settings['auto_pin_new_posts'] ? '✅' : '☐') . ' Auto-pin', 'callback_data' => 'toggle:' . $channelId . ':auto_pin_new_posts']],
            [['text' => ($settings['post_approval_required'] ? '✅' : '☐') . ' Approvals', 'callback_data' => 'toggle:' . $channelId . ':post_approval_required']],
            [['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]
        ];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Toggle setting
     */
    private function toggleSetting(Container $container, int $userId, int $chatId, int $channelId, string $setting, ?int $messageId): void
    {
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $settings = $channelService->getSettings($channelId);
        $currentValue = $settings[$setting] ?? 0;
        $newValue = $currentValue ? 0 : 1;

        try {
            $channelService->updateSetting($channelId, $setting, $newValue);
            $this->showSettings($container, $userId, $chatId, $channelId, $messageId);
        } catch (\Exception $e) {
            $telegram->answer($chatId, "❌ Error: " . $e->getMessage());
        }
    }

    /**
     * Format toggle display
     */
    private function formatToggle(string $label, $value): string
    {
        $icon = $value ? '✅' : '☐';
        return "$icon $label\n";
    }
}
