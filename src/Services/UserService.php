<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * User Service
 * 
 * Manages user data and sessions
 */
class UserService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get or create user
     */
    public function getOrCreateUser(array $from): array
    {
        $user = $this->getUser($from['id']);

        if (!$user) {
            $this->createUser($from);
            $user = $this->getUser($from['id']);
        } else {
            // Update last active
            $this->updateLastActive($from['id']);
        }

        return $user;
    }

    /**
     * Create user
     */
    public function createUser(array $from): void
    {
        $this->db->execute(
            "INSERT INTO users (user_id, username, first_name, last_name, language_code)
             VALUES (?, ?, ?, ?, ?)",
            [
                $from['id'],
                $from['username'] ?? null,
                $from['first_name'] ?? null,
                $from['last_name'] ?? null,
                $from['language_code'] ?? 'en'
            ]
        );
    }

    /**
     * Get user by ID
     */
    public function getUser(int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Update last active timestamp
     */
    public function updateLastActive(int $userId): void
    {
        $this->db->execute(
            "UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Update user settings
     */
    public function updateUser(int $userId, array $data): void
    {
        $sets = [];
        $values = [];

        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $values[] = $value;
        }

        $values[] = $userId;

        $this->db->execute(
            "UPDATE users SET " . implode(', ', $sets) . " WHERE user_id = ?",
            $values
        );
    }

    /**
     * Get session
     */
    public function getSession(int $userId): ?array
    {
        $session = $this->db->fetchOne(
            "SELECT * FROM sessions WHERE user_id = ?",
            [$userId]
        );

        if (!$session) {
            return null;
        }

        return [
            'state' => $session['state'],
            'data' => json_decode($session['data'] ?? '{}', true)
        ];
    }

    /**
     * Set session
     */
    public function setSession(int $userId, string $state, array $data = []): void
    {
        $this->db->execute(
            "INSERT INTO sessions (user_id, state, data, updated_at)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE state = ?, data = ?, updated_at = CURRENT_TIMESTAMP",
            [
                $userId,
                $state,
                json_encode($data),
                $state,
                json_encode($data)
            ]
        );
    }

    /**
     * Clear session
     */
    public function clearSession(int $userId): void
    {
        $this->db->execute(
            "DELETE FROM sessions WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Check rate limit
     */
    public function checkRateLimit(int $userId, int $limit, int $window = 60): bool
    {
        $rateLimit = $this->db->fetchOne(
            "SELECT * FROM rate_limits WHERE user_id = ?",
            [$userId]
        );

        $now = time();

        if (!$rateLimit) {
            $this->db->execute(
                "INSERT INTO rate_limits (user_id, action_count, window_start) VALUES (?, 1, FROM_UNIXTIME(?))",
                [$userId, $now]
            );
            return true;
        }

        $windowStart = strtotime($rateLimit['window_start']);

        // Reset window if expired
        if ($now - $windowStart >= $window) {
            $this->db->execute(
                "UPDATE rate_limits SET action_count = 1, window_start = FROM_UNIXTIME(?) WHERE user_id = ?",
                [$now, $userId]
            );
            return true;
        }

        // Check if under limit
        if ($rateLimit['action_count'] < $limit) {
            $this->db->execute(
                "UPDATE rate_limits SET action_count = action_count + 1 WHERE user_id = ?",
                [$userId]
            );
            return true;
        }

        return false;
    }
}
