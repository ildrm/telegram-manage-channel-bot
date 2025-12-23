<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Approval Service
 * 
 * Manages post approval workflows
 */
class ApprovalService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create approval workflow
     */
    public function createWorkflow(int $channelId, int $userId, string $name, int $requiredApprovers = 1): int
    {
        return $this->db->insert(
            "INSERT INTO approval_workflows (channel_id, created_by, name, required_approvers, active)
             VALUES (?, ?, ?, ?, 1)",
            [$channelId, $userId, $name, $requiredApprovers]
        );
    }

    /**
     * Get workflow
     */
    public function getWorkflow(int $workflowId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM approval_workflows WHERE id = ?",
            [$workflowId]
        );
    }

    /**
     * Get channel workflows
     */
    public function getChannelWorkflows(int $channelId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM approval_workflows WHERE channel_id = ? ORDER BY created_at DESC",
            [$channelId]
        );
    }

    /**
     * Submit post for approval
     */
    public function submitForApproval(int $postId, int $workflowId, int $submittedBy): int
    {
        return $this->db->insert(
            "INSERT INTO post_approvals (post_id, workflow_id, submitted_by, status)
             VALUES (?, ?, ?, 'pending')",
            [$postId, $workflowId, $submittedBy]
        );
    }

    /**
     * Get pending approvals for user
     */
    public function getPendingApprovals(int $userId, int $channelId): array
    {
        return $this->db->fetchAll(
            "SELECT pa.*, p.content, p.content_type, w.name as workflow_name
             FROM post_approvals pa
             JOIN posts p ON pa.post_id = p.id
             JOIN approval_workflows w ON pa.workflow_id = w.id
             WHERE w.channel_id = ? 
             AND pa.status = 'pending'
             ORDER BY pa.created_at DESC",
            [$channelId]
        );
    }

    /**
     * Approve post
     */
    public function approvePost(int $approvalId, int $userId, ?string $comment = null): bool
    {
        $this->db->beginTransaction();

        try {
            // Add approval action
            $this->db->execute(
                "INSERT INTO approval_actions (approval_id, user_id, action, comment)
                 VALUES (?, ?, 'approved', ?)",
                [$approvalId, $userId, $comment]
            );

            // Get approval
            $approval = $this->db->fetchOne(
                "SELECT pa.*, w.required_approvers
                 FROM post_approvals pa
                 JOIN approval_workflows w ON pa.workflow_id = w.id
                 WHERE pa.id = ?",
                [$approvalId]
            );

            // Count approvals
            $approvalCount = $this->db->fetchOne(
                "SELECT COUNT(*) as cnt 
                 FROM approval_actions 
                 WHERE approval_id = ? AND action = 'approved'",
                [$approvalId]
            );

            $count = (int)($approvalCount['cnt'] ?? 0);

            // If enough approvals, mark as approved
            if ($count >= $approval['required_approvers']) {
                $this->db->execute(
                    "UPDATE post_approvals SET status = 'approved' WHERE id = ?",
                    [$approvalId]
                );

                // Update post status
                $this->db->execute(
                    "UPDATE posts SET approval_status = 'approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$userId, $approval['post_id']]
                );
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Failed to approve post: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reject post
     */
    public function rejectPost(int $approvalId, int $userId, ?string $comment = null): bool
    {
        $this->db->beginTransaction();

        try {
            // Add rejection action
            $this->db->execute(
                "INSERT INTO approval_actions (approval_id, user_id, action, comment)
                 VALUES (?, ?, 'rejected', ?)",
                [$approvalId, $userId, $comment]
            );

            // Get approval
            $approval = $this->db->fetchOne(
                "SELECT post_id FROM post_approvals WHERE id = ?",
                [$approvalId]
            );

            // Update approval status
            $this->db->execute(
                "UPDATE post_approvals SET status = 'rejected' WHERE id = ?",
                [$approvalId]
            );

            // Update post status
            $this->db->execute(
                "UPDATE posts SET approval_status = 'rejected' WHERE id = ?",
                [$approval['post_id']]
            );

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Failed to reject post: " . $e->getMessage());
            return false;
        }
    }
}
