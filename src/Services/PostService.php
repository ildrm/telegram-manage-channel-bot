<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Post Service
 * 
 * Manages posts, drafts, and scheduled posts
 */
class PostService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create post
     */
    public function createPost(int $channelId, int $messageId, int $userId, array $data): int
    {
        return $this->db->insert(
            "INSERT INTO posts (channel_id, message_id, user_id, campaign_id, content_type, content, media_id, buttons, approval_status, approved_by, approved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $channelId,
                $messageId,
                $userId,
                $data['campaign_id'] ?? null,
                $data['content_type'] ?? 'text',
                $data['content'] ?? null,
                $data['media_id'] ?? null,
                isset($data['buttons']) ? json_encode($data['buttons']) : null,
                $data['approval_status'] ?? 'approved',
                $data['approved_by'] ?? null,
                $data['approved_at'] ?? null
            ]
        );
    }

    /**
     * Update post
     */
    public function updatePost(int $postId, array $data): bool
    {
        $sets = [];
        $values = [];

        if (isset($data['content'])) {
            $sets[] = "content = ?";
            $values[] = $data['content'];
        }

        if (isset($data['media_id'])) {
            $sets[] = "media_id = ?";
            $values[] = $data['media_id'];
        }

        if (!empty($sets)) {
            $sets[] = "updated_at = CURRENT_TIMESTAMP";
            $values[] = $postId;

            $this->db->execute(
                "UPDATE posts SET " . implode(', ', $sets) . " WHERE id = ?",
                $values
            );
            return true;
        }

        return false;
    }

    /**
     * Get post
     */
    public function getPost(int $postId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM posts WHERE id = ? AND is_deleted = 0",
            [$postId]
        );
    }

    /**
     * Get channel posts
     */
    public function getChannelPosts(int $channelId, int $offset = 0, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM posts
             WHERE channel_id = ? AND is_deleted = 0
             ORDER BY posted_at DESC
             LIMIT ? OFFSET ?",
            [$channelId, $limit, $offset]
        );
    }

    /**
     * Count channel posts
     */
    public function countChannelPosts(int $channelId): int
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM posts WHERE channel_id = ? AND is_deleted = 0",
            [$channelId]
        );
        return (int)($result['cnt'] ?? 0);
    }

    /**
     * Soft delete post
     */
    public function deletePost(int $postId): void
    {
        $this->db->execute(
            "UPDATE posts SET is_deleted = 1, deleted_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$postId]
        );
    }

    /**
     * Create draft
     */
    public function createDraft(int $channelId, int $userId, array $data): int
    {
        return $this->db->insert(
            "INSERT INTO drafts (channel_id, user_id, name, content_type, content, media_id, buttons)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $channelId,
                $userId,
                $data['name'] ?? null,
                $data['content_type'] ?? 'text',
                $data['content'] ?? null,
                $data['media_id'] ?? null,
                isset($data['buttons']) ? json_encode($data['buttons']) : null
            ]
        );
    }

    /**
     * Get draft
     */
    public function getDraft(int $draftId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM drafts WHERE id = ?",
            [$draftId]
        );
    }

    /**
     * Get user's drafts for channel
     */
    public function getChannelDrafts(int $channelId, int $userId, int $offset = 0, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM drafts
             WHERE channel_id = ? AND user_id = ?
             ORDER BY updated_at DESC
             LIMIT ? OFFSET ?",
            [$channelId, $userId, $limit, $offset]
        );
    }

    /**
     * Update draft
     */
    public function updateDraft(int $draftId, array $data): void
    {
        $sets = [];
        $values = [];

        foreach ($data as $key => $value) {
            if ($key === 'buttons' && is_array($value)) {
                $value = json_encode($value);
            }
            $sets[] = "{$key} = ?";
            $values[] = $value;
        }

        if (!empty($sets)) {
            $sets[] = "updated_at = CURRENT_TIMESTAMP";
            $values[] = $draftId;

            $this->db->execute(
                "UPDATE drafts SET " . implode(', ', $sets) . " WHERE id = ?",
                $values
            );
        }
    }

    /**
     * Delete draft
     */
    public function deleteDraft(int $draftId): void
    {
        $this->db->execute("DELETE FROM drafts WHERE id = ?", [$draftId]);
    }

    /**
     * Create scheduled post
     */
    public function createScheduledPost(int $channelId, int $userId, int $scheduleTime, array $data): int
    {
        return $this->db->insert(
            "INSERT INTO scheduled (channel_id, user_id, campaign_id, content_type, content, media_id, buttons, schedule_time, timezone, recurring, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?, ?, 'pending')",
            [
                $channelId,
                $userId,
                $data['campaign_id'] ?? null,
                $data['content_type'] ?? 'text',
                $data['content'] ?? null,
                $data['media_id'] ?? null,
                isset($data['buttons']) ? json_encode($data['buttons']) : null,
                $scheduleTime,
                $data['timezone'] ?? 'UTC',
                isset($data['recurring']) ? json_encode($data['recurring']) : null
            ]
        );
    }

    /**
     * Get scheduled post
     */
    public function getScheduledPost(int $scheduledId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM scheduled WHERE id = ?",
            [$scheduledId]
        );
    }

    /**
     * Get pending scheduled posts
     */
    public function getPendingScheduledPosts(int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM scheduled
             WHERE status = 'pending' AND schedule_time <= CURRENT_TIMESTAMP
             ORDER BY schedule_time
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Get channel scheduled posts
     */
    public function getChannelScheduledPosts(int $channelId, int $offset = 0, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM scheduled
             WHERE channel_id = ? AND status = 'pending'
             ORDER BY schedule_time
             LIMIT ? OFFSET ?",
            [$channelId, $limit, $offset]
        );
    }

    /**
     * Update scheduled post status
     */
    public function updateScheduledStatus(int $scheduledId, string $status): void
    {
        $this->db->execute(
            "UPDATE scheduled SET status = ? WHERE id = ?",
            [$status, $scheduledId]
        );
    }

    /**
     * Reschedule recurring post
     */
    public function reschedulePost(int $scheduledId, int $nextTime): void
    {
        $this->db->execute(
            "UPDATE scheduled SET schedule_time = FROM_UNIXTIME(?), status = 'pending' WHERE id = ?",
            [$nextTime, $scheduledId]
        );
    }

    /**
     * Delete scheduled post
     */
    public function deleteScheduledPost(int $scheduledId): void
    {
        $this->db->execute("DELETE FROM scheduled WHERE id = ?", [$scheduledId]);
    }
}
