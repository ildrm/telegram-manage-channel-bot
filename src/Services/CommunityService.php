<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Community Service
 * 
 * Manage channel community features
 */
class CommunityService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Link discussion group
     */
    public function linkDiscussionGroup(int $channelId, int $groupId): void
    {
        $this->db->execute(
            "UPDATE channel_settings SET value = ? WHERE channel_id = ? AND setting_key = 'discussion_group'",
            [$groupId, $channelId]
        );
    }

    /**
     * Get channel comments
     */
    public function getComments(int $channelId, int $messageId): array
    {
        $setting = $this->db->fetchOne(
            "SELECT value FROM channel_settings WHERE channel_id = ? AND setting_key = 'discussion_group'",
            [$channelId]
        );

        if (!$setting) {
            return [];
        }

        // In real implementation, would fetch from linked discussion group
        return [];
    }

    /**
     * Auto-approve comments
     */
    public function autoApproveComment(int $channelId, int $commentId): bool
    {
        // Check auto-approve settings
        $setting = $this->db->fetchOne(
            "SELECT value FROM channel_settings WHERE channel_id = ? AND setting_key = 'auto_approve_comments'",
            [$channelId]
        );

        return ($setting['value'] ?? '0') === '1';
    }

    /**
     * Get blacklisted words
     */
    public function getBlacklistedWords(int $channelId): array
    {
        $setting = $this->db->fetchOne(
            "SELECT value FROM channel_settings WHERE channel_id = ? AND setting_key = 'blacklist_words'",
            [$channelId]
        );

        if (!$setting || empty($setting['value'])) {
            return [];
        }

        return json_decode($setting['value'], true) ?? [];
    }

    /**
     * Check if comment contains blacklisted words
     */
    public function checkBlacklist(string $text, array $blacklist): bool
    {
        $textLower = mb_strtolower($text);
        
        foreach ($blacklist as $word) {
            if (mb_strpos($textLower, mb_strtolower($word)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create poll in channel
     */
    public function createPoll(int $channelId, string $question, array $options, array $settings = []): ?array
    {
        // Store poll data
        $pollId = $this->db->insert(
            "INSERT INTO polls (channel_id, question, options, is_anonymous, allows_multiple, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $channelId,
                $question,
                json_encode($options),
                $settings['is_anonymous'] ?? true,
                $settings['allows_multiple'] ?? false
            ]
        );

        return [
            'poll_id' => $pollId,
            'question' => $question,
            'options' => $options
        ];
    }

    /**
     * Create survey
     */
    public function createSurvey(int $channelId, string $title, array $questions): int
    {
        $surveyId = $this->db->insert(
            "INSERT INTO surveys (channel_id, title, questions, created_at)
             VALUES (?, ?, ?, NOW())",
            [$channelId, $title, json_encode($questions)]
        );

        return $surveyId;
    }

    /**
     * Get reaction analytics
     */
    public function getReactionAnalytics(int $channelId, int $days = 30): array
    {
        return $this->db->fetchAll(
            "SELECT reaction_type, COUNT(*) as count
             FROM post_reactions pr
             JOIN posts p ON pr.post_id = p.id
             WHERE p.channel_id = ?
             AND pr.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY reaction_type
             ORDER BY count DESC",
            [$channelId, $days]
        );
    }
}
