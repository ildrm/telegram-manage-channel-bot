<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Multi-Channel Service
 * 
 * Handles cross-posting to multiple channels
 */
class MultiChannelService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create channel group
     */
    public function createChannelGroup(int $userId, string $name, ?string $description = null): int
    {
        return $this->db->insert(
            "INSERT INTO channel_groups (user_id, name, description)
             VALUES (?, ?, ?)",
            [$userId, $name, $description]
        );
    }

    /**
     * Add channel to group
     */
    public function addChannelToGroup(int $groupId, int $channelId): void
    {
        $this->db->insert(
            "INSERT INTO channel_group_members (group_id, channel_id)
             VALUES (?, ?)",
            [$groupId, $channelId]
        );
    }

    /**
     * Remove channel from group
     */
    public function removeChannelFromGroup(int $groupId, int $channelId): void
    {
        $this->db->execute(
            "DELETE FROM channel_group_members WHERE group_id = ? AND channel_id = ?",
            [$groupId, $channelId]
        );
    }

    /**
     * Get user's channel groups
     */
    public function getUserGroups(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT cg.*, COUNT(cgm.channel_id) as channel_count
             FROM channel_groups cg
             LEFT JOIN channel_group_members cgm ON cg.id = cgm.group_id
             WHERE cg.user_id = ?
             GROUP BY cg.id
             ORDER BY cg.created_at DESC",
            [$userId]
        );
    }

    /**
     * Get group channels
     */
    public function getGroupChannels(int $groupId): array
    {
        return $this->db->fetchAll(
            "SELECT c.*
             FROM channels c
             JOIN channel_group_members cgm ON c.channel_id = cgm.channel_id
             WHERE cgm.group_id = ?",
            [$groupId]
        );
    }

    /**
     * Cross-post to multiple channels
     */
    public function crossPost(array $channelIds, int $userId, array $content, $telegram): array
    {
        $results = [];

        foreach ($channelIds as $channelId) {
            try {
                $params = ['chat_id' => $channelId];

                switch ($content['content_type']) {
                    case 'photo':
                        $params['photo'] = $content['media_id'];
                        $params['caption'] = $content['content'];
                        $result = $telegram->sendPhoto($params);
                        break;

                    case 'video':
                        $params['video'] = $content['media_id'];
                        $params['caption'] = $content['content'];
                        $result = $telegram->sendVideo($params);
                        break;

                    case 'document':
                        $params['document'] = $content['media_id'];
                        $params['caption'] = $content['content'];
                        $result = $telegram->sendDocument($params);
                        break;

                    default: // text
                        $params['text'] = $content['content'];
                        $result = $telegram->sendMessage($params);
                        break;
                }

                $results[$channelId] = [
                    'success' => (bool)$result,
                    'message_id' => $result['message_id'] ?? null
                ];
            } catch (\Exception $e) {
                error_log("Cross-post failed for channel $channelId: " . $e->getMessage());
                $results[$channelId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
