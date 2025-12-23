<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\NotificationService;
use App\Telegram\Client;

/**
 * Notification Module
 * 
 * Handles user notifications display
 */
class NotificationModule implements PluginInterface
{
    public function register(Container $container): void
    {
        $container->singleton(NotificationService::class);
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

        // View notifications
        if ($data === 'notifications') {
            $telegram->answer($query['id']);
            $this->showNotifications($container, $userId, $chatId, $messageId);
            return;
        }

        // Mark all as read
        if ($data === 'mark_all_read') {
            $telegram->answer($query['id']);
            
            $notificationService = $container->make(NotificationService::class);
            $notificationService->markAllAsRead($userId);
            
            $this->showNotifications($container, $userId, $chatId, $messageId);
            return;
        }
    }

    /**
     * Show notifications
     */
    private function showNotifications(Container $container, int $userId, int $chatId, ?int $messageId): void
    {
        $notificationService = $container->make(NotificationService::class);
        $telegram = $container->make(Client::class);

        $notifications = $notificationService->getUserNotifications($userId, 0, 10);
        $unreadCount = $notificationService->getUnreadCount($userId);

        $text = "🔔 <b>Notifications</b>";
        
        if ($unreadCount > 0) {
            $text .= " ($unreadCount unread)";
        }
        
        $text .= "\n\n";

        if (empty($notifications)) {
            $text .= "No notifications yet.";
            $keyboard = [[['text' => '« Back',

 'callback_data' => 'menu']]];
        } else {
            foreach ($notifications as $notif) {
                $icon = $notif['is_read'] ? '📨' : '🆕';
                $text .= "$icon <b>" . htmlspecialchars($notif['title']) . "</b>\n";
                $text .= "  " . htmlspecialchars($notif['message']) . "\n";
                $text .= "  " . date('M d, H:i', strtotime($notif['created_at'])) . "\n\n";
            }

            $keyboard = [];
            
            if ($unreadCount > 0) {
                $keyboard[] = [['text' => '✅ Mark All Read', 'callback_data' => 'mark_all_read']];
            }
            
            $keyboard[] = [['text' => '« Back', 'callback_data' => 'menu']];
        }

        if ($messageId) {
            $telegram->edit($chatId, $messageId, $text, $keyboard);
        } else {
            $telegram->send($chatId, $text, $keyboard);
        }
    }
}
