<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Notification Service
 * 
 * Manages user notifications
 */
class NotificationService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create notification
     */
    public function createNotification(int $userId, string $type, string $title, string $message, ?int $relatedId = null): int
    {
        return $this->db->insert(
            "INSERT INTO notifications (user_id, type, title, message, related_id)
             VALUES (?, ?, ?, ?, ?)",
            [$userId, $type, $title, $message, $relatedId]
        );
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(int $userId, int $offset = 0, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM notifications 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(int $userId): int
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM notifications 
             WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        return (int)($result['cnt'] ?? 0);
    }

    /**
     * Mark as read
     */
    public function markAsRead(int $notificationId): void
    {
        $this->db->execute(
            "UPDATE notifications SET is_read = 1, read_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$notificationId]
        );
    }

    /**
     * Mark all as read
     */ 
    public function markAllAsRead(int $userId): void
    {
        $this->db->execute(
            "UPDATE notifications SET is_read = 1, read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    /**
     * Delete notification
     */
    public function deleteNotification(int $notificationId): void
    {
        $this->db->execute("DELETE FROM notifications WHERE id = ?", [$notificationId]);
    }
}
