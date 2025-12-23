<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Backup Service
 * 
 * Manages channel data backups
 */
class BackupService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create backup
     */
    public function createBackup(int $channelId, int $userId, string $type = 'full'): int
    {
        $this->db->beginTransaction();

        try {
            // Get channel data
            $channel = $this->db->fetchOne(
                "SELECT * FROM channels WHERE channel_id = ?",
                [$channelId]
            );

            // Get posts
            $posts = $this->db->fetchAll(
                "SELECT * FROM posts WHERE channel_id = ? AND is_deleted = 0",
                [$channelId]
            );

            // Get settings
            $settings = $this->db->fetchOne(
                "SELECT * FROM channel_settings WHERE channel_id = ?",
                [$channelId]
            );

            // Prepare backup data
            $backupData = [
                'channel' => $channel,
                'posts' => $posts,
                'settings' => $settings,
                'post_count' => count($posts),
                'exported_at' => date('Y-m-d H:i:s')
            ];

            // Create backup record
            $backupId = $this->db->insert(
                "INSERT INTO backups (channel_id, user_id, backup_type, post_count, data)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $channelId,
                    $userId,
                    $type,
                    count($posts),
                    json_encode($backupData)
                ]
            );

            $this->db->commit();
            return $backupId;
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Failed to create backup: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get backup
     */
    public function getBackup(int $backupId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM backups WHERE id = ?",
            [$backupId]
        );
    }

    /**
     * Get channel backups
     */
    public function getChannelBackups(int $channelId, int $offset = 0, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM backups 
             WHERE channel_id = ? 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$channelId, $limit, $offset]
        );
    }

    /**
     * Delete backup
     */
    public function deleteBackup(int $backupId): void
    {
        $this->db->execute("DELETE FROM backups WHERE id = ?", [$backupId]);
    }

    /**
     * Export backup as JSON file
     */
    public function exportToFile(int $backupId, string $directory): ?string
    {
        $backup = $this->getBackup($backupId);

        if (!$backup) {
            return null;
        }

        $filename = "backup_{$backup['channel_id']}_" . date('Ymd_His', strtotime($backup['created_at'])) . ".json";
        $filepath = rtrim($directory, '/\\') . '/' . $filename;

        $data = json_decode($backup['data'], true);
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));

        return $filepath;
    }
}
