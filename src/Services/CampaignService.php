<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Campaign Service
 * 
 * Manages campaigns and multi-post grouping
 */
class CampaignService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create campaign
     */
    public function createCampaign(int $userId, string $name, ?string $description = null): int
    {
        return $this->db->insert(
            "INSERT INTO campaigns (user_id, name, description, status)
             VALUES (?, ?, ?, 'draft')",
            [$userId, $name, $description]
        );
    }

    /**
     * Get campaign
     */
    public function getCampaign(int $campaignId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM campaigns WHERE id = ?",
            [$campaignId]
        );
    }

    /**
     * Get user's campaigns
     */
    public function getUserCampaigns(int $userId, int $offset = 0, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT c.*, COUNT(p.id) as post_count
             FROM campaigns c
             LEFT JOIN posts p ON c.id = p.campaign_id
             WHERE c.user_id = ?
             GROUP BY c.id
             ORDER BY c.created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    /**
     * Update campaign
     */
    public function updateCampaign(int $campaignId, array $data): void
    {
        $sets = [];
        $values = [];

        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $values[] = $value;
        }

        if (!empty($sets)) {
            $sets[] = "updated_at = CURRENT_TIMESTAMP";
            $values[] = $campaignId;

            $this->db->execute(
                "UPDATE campaigns SET " . implode(', ', $sets) . " WHERE id = ?",
                $values
            );
        }
    }

    /**
     * Delete campaign
     */
    public function deleteCampaign(int $campaignId): void
    {
        $this->db->execute("DELETE FROM campaigns WHERE id = ?", [$campaignId]);
    }

    /**
     * Get campaign posts
     */
    public function getCampaignPosts(int $campaignId, int $offset = 0, int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, c.title as channel_title
             FROM posts p
             JOIN channels c ON p.channel_id = c.channel_id
             WHERE p.campaign_id = ? AND p.is_deleted = 0
             ORDER BY p.posted_at DESC
             LIMIT ? OFFSET ?",
            [$campaignId, $limit, $offset]
        );
    }

    /**
     * Count campaign posts
     */
    public function countCampaignPosts(int $campaignId): int
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM posts WHERE campaign_id = ? AND is_deleted = 0",
            [$campaignId]
        );
        return (int)($result['cnt'] ?? 0);
    }

    /**
     * Start campaign
     */
    public function startCampaign(int $campaignId): void
    {
        $this->updateCampaign($campaignId, [
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * End campaign
     */
    public function endCampaign(int $campaignId): void
    {
        $this->updateCampaign($campaignId, [
            'status' => 'completed',
            'end_date' => date('Y-m-d H:i:s')
        ]);
    }
}
