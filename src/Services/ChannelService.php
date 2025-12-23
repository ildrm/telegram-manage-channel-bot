<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Channel Service
 * 
 * Manages channels and channel ownership
 */
class ChannelService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get or create channel
     */
    public function getOrCreateChannel(int $channelId, array $chatData): array
    {
        $channel = $this->getChannel($channelId);

        if (!$channel) {
            $this->createChannel($channelId, $chatData);
            $channel = $this->getChannel($channelId);
        } else {
            // Update channel info
            $this->updateChannel($channelId, $chatData);
        }

        return $channel;
    }

    /**
     * Create channel
     */
    public function createChannel(int $channelId, array $chatData): void
    {
        $this->db->execute(
            "INSERT INTO channels (channel_id, title, username, type, description)
             VALUES (?, ?, ?, ?, ?)",
            [
                $channelId,
                $chatData['title'] ?? 'Unknown',
                $chatData['username'] ?? null,
                $chatData['type'] ?? 'channel',
                $chatData['description'] ?? null
            ]
        );

        // Create default settings
        $this->db->execute(
            "INSERT INTO channel_settings (channel_id) VALUES (?)",
            [$channelId]
        );
    }

    /**
     * Update channel
     */
    public function updateChannel(int $channelId, array $chatData): void
    {
        $this->db->execute(
            "UPDATE channels SET title = ?, username = ?, type = ?, updated_at = CURRENT_TIMESTAMP
             WHERE channel_id = ?",
            [
                $chatData['title'] ?? 'Unknown',
                $chatData['username'] ?? null,
                $chatData['type'] ?? 'channel',
                $channelId
            ]
        );
    }

    /**
     * Get channel by ID
     */
    public function getChannel(int $channelId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM channels WHERE channel_id = ?",
            [$channelId]
        );
    }

    /**
     * Add channel owner
     */
    public function addOwner(int $channelId, int $userId, bool $isCreator = false): void
    {
        $this->db->execute(
            "INSERT INTO channel_owners (channel_id, user_id, is_creator)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE is_creator = ?",
            [$channelId, $userId, $isCreator ? 1 : 0, $isCreator ? 1 : 0]
        );
    }

    /**
     * Remove channel owner
     */
    public function removeOwner(int $channelId, int $userId): void
    {
        $this->db->execute(
            "DELETE FROM channel_owners WHERE channel_id = ? AND user_id = ?",
            [$channelId, $userId]
        );
    }

    /**
     * Get user's channels
     */
    public function getUserChannels(int $userId, int $offset = 0, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT c.*, co.is_creator
             FROM channels c
             JOIN channel_owners co ON c.channel_id = co.channel_id
             WHERE co.user_id = ?
             AND c.is_active = 1
             ORDER BY c.title
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    /**
     * Count user's channels
     */
    public function countUserChannels(int $userId): int
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt
             FROM channel_owners co
             JOIN channels c ON co.channel_id = c.channel_id
             WHERE co.user_id = ?
             AND c.is_active = 1",
            [$userId]
        );
        return (int)($result['cnt'] ?? 0);
    }

    /**
     * Deactivate channel
     */
    public function deactivateChannel(int $channelId): void
    {
        $this->db->execute(
            "UPDATE channels SET is_active = 0 WHERE channel_id = ?",
            [$channelId]
        );
    }

    /**
     * Get channel settings
     */
    public function getSettings(int $channelId): array
    {
        $settings = $this->db->fetchOne(
            "SELECT * FROM channel_settings WHERE channel_id = ?",
            [$channelId]
        );

        if (!$settings) {
            // Create default settings
            $this->db->execute(
                "INSERT INTO channel_settings (channel_id) VALUES (?)",
                [$channelId]
            );
            return $this->getSettings($channelId);
        }

        return $settings;
    }

    /**
     * Update channel setting
     */
    public function updateSetting(int $channelId, string $key, $value): void
    {
        // Validate column exists
        $validColumns = [
            'reactions_enabled', 'default_reactions', 'auto_react', 'auto_react_emoji',
            'views_enabled', 'comments_enabled', 'anti_spam_enabled', 'spam_blacklist',
            'captcha_enabled', 'watermark', 'signature', 'start_payload_message',
            'allow_comments_control', 'auto_pin_new_posts', 'post_approval_required',
            'default_timezone', 'branding_footer'
        ];

        if (!in_array($key, $validColumns)) {
            throw new \InvalidArgumentException("Invalid setting key: {$key}");
        }

        $this->db->execute(
            "UPDATE channel_settings SET {$key} = ? WHERE channel_id = ?",
            [$value, $channelId]
        );
    }

    /**
     * Delete channel
     */
    public function deleteChannel(int $channelId): void
    {
        $this->db->execute("DELETE FROM channels WHERE channel_id = ?", [$channelId]);
    }
}
