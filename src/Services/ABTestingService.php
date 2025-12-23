<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * A/B Testing Service
 * 
 * Test different content variations
 */
class ABTestingService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create A/B test
     */
    public function createTest(int $channelId, int $userId, array $variants, array $options = []): int
    {
        $testId = $this->db->insert(
            "INSERT INTO ab_tests (channel_id, user_id, name, variants, test_duration, status)
             VALUES (?, ?, ?, ?, ?, 'active')",
            [
                $channelId,
                $userId,
                $options['name'] ?? 'A/B Test',
                json_encode($variants),
                $options['duration'] ?? 24 // hours
            ]
        );

        // Schedule variants
        foreach ($variants as $index => $variant) {
            $this->db->insert(
                "INSERT INTO ab_test_variants (test_id, variant_name, content, percentage)
                 VALUES (?, ?, ?, ?)",
                [
                    $testId,
                    chr(65 + $index), // A, B, C, etc.
                    json_encode($variant),
                    $variant['percentage'] ?? (100 / count($variants))
                ]
            );
        }

        return $testId;
    }

    /**
     * Get winning variant
     */
    public function getWinner(int $testId): ?array
    {
        $variants = $this->db->fetchAll(
            "SELECT atv.*, 
                    COUNT(DISTINCT pa.post_id) as posts_count,
                    AVG(pa.views) as avg_views,
                    AVG(pa.engagement_rate) as avg_engagement
             FROM ab_test_variants atv
             LEFT JOIN posts p ON p.ab_test_variant_id = atv.id
             LEFT JOIN post_analytics pa ON pa.post_id = p.id
             WHERE atv.test_id = ?
             GROUP BY atv.id
             ORDER BY avg_engagement DESC",
            [$testId]
        );

        return $variants[0] ?? null;
    }

    /**
     * Complete test and analyze results
     */
    public function completeTest(int $testId): array
    {
        $winner = $this->getWinner($testId);
        
        $this->db->execute(
            "UPDATE ab_tests SET status = 'completed', winner_variant_id = ?, completed_at = NOW() WHERE id = ?",
            [$winner['id'] ?? null, $testId]
        );

        return [
            'test_id' => $testId,
            'winner' => $winner,
            'status' => 'completed'
        ];
    }
}
