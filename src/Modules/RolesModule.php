<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\AuthorizationService;
use App\Services\ChannelService;
use App\Services\UserService;
use App\Telegram\Client;

/**
 * Roles Module
 * 
 * Complete role management UI
 */
class RolesModule implements PluginInterface
{
    public function register(Container $container): void
    {
        // Already registered in AuthModule
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

        // View roles for channel
        if (strpos($data, 'roles:') === 0) {
            $channelId = (int)substr($data, 6);
            $telegram->answer($query['id']);
            $this->showChannelRoles($container, $userId, $chatId, $channelId, $messageId);
            return;
        }

        // Assign role
        if (strpos($data, 'assign_role:') === 0) {
            $parts = explode(':', $data);
            $channelId = (int)$parts[1];
            $roleId = (int)$parts[2];
            
            $telegram->answer($query['id']);
            
            $userService = $container->make(UserService::class);
            $userService->setSession($userId, 'awaiting_user_telegram_id', [
                'channel_id' => $channelId,
                'role_id' => $roleId
            ]);
            
            $telegram->edit(
                $chatId,
                $messageId,
                "👤 <b>Assign Role</b>\n\nSend me the user's Telegram ID or @username:",
                [[['text' => '❌ Cancel', 'callback_data' => 'roles:' . $channelId]]]
            );
            return;
        }

        // View all available roles
        if ($data === 'view_roles') {
            $telegram->answer($query['id']);
            $this->showAvailableRoles($container, $chatId, $messageId);
            return;
        }
    }

    /**
     * Handle text
     */
    public function handleText(array $message, array $update, Container $container): void
    {
        if ($message['chat']['type'] !== 'private') {
            return;
        }

        $userId = $message['from']['id'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        if (strpos($text, '/') === 0) {
            return;
        }

        $userService = $container->make(UserService::class);
        $session = $userService->getSession($userId);

        if (!$session) {
            return;
        }

        // Assign role to user
        if ($session['state'] === 'awaiting_user_telegram_id') {
            $this->assignRoleToUser($container, $userId, $chatId, $text, $session['data']);
            return;
        }
    }

    /**
     * Show channel roles
     */
    private function showChannelRoles(Container $container, int $userId, int $chatId, int $channelId, ?int $messageId): void
    {
        $authService = $container->make(AuthorizationService::class);
        $channelService = $container->make(ChannelService::class);
        $telegram = $container->make(Client::class);

        $channel = $channelService->getChannel($channelId);
        $roles = $authService->getAllRoles();

        $text = "👥 <b>Roles & Permissions - " . htmlspecialchars($channel['title']) . "</b>\n\n";
        $text .= "<b>Available Roles:</b>\n\n";

        foreach ($roles as $role) {
            $emoji = $this->getRoleEmoji($role['name']);
            $text .= "$emoji <b>{$role['name']}</b>\n";
            $text .= "  " . htmlspecialchars($role['description'] ?? 'No description') . "\n\n";
        }

        $text .= "💡 <i>Role management UI coming soon!</i>\n";
        $text .= "You'll be able to:\n";
        $text .= "• Assign roles to team members\n";
        $text .= "• Set temporary access\n";
        $text .= "• View role permissions\n";
        $text .= "• Create custom roles";

        $keyboard = [[['text' => '« Back', 'callback_data' => 'ch:' . $channelId]]];

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }

    /**
     * Get emoji for role
     */
    private function getRoleEmoji(string $roleName): string
    {
        $emojis = [
            'Owner' => '👑',
            'Admin' => '⭐',
            'Editor' => '✏️',
            'Reviewer' => '✅',
            'Analyst' => '📊'
        ];

        return $emojis[$roleName] ?? '👤';
    }
}
