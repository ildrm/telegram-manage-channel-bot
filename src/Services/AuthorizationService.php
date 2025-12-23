<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Core\Config;
use PDO;

/**
 * Authorization Service
 * 
 * Handles user permissions and role-based access control
 */
class AuthorizationService
{
    private Database $db;
    private Config $config;
    private array $permissionCache = [];

    public function __construct(Database $db, Config $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Check if user owns a channel
     */
    public function userOwnsChannel(int $userId, int $channelId): bool
    {
        $result = $this->db->fetchOne(
            "SELECT 1 FROM channel_owners WHERE user_id = ? AND channel_id = ?",
            [$userId, $channelId]
        );
        return $result !== null;
    }

    /**
     * Check if user has permission for a channel
     */
    public function userHasPermission(int $userId, int $channelId, string $permission): bool
    {
        // Check cache
        $cacheKey = "{$userId}:{$channelId}:{$permission}";
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }

        // Owner always has all permissions
        if ($this->userOwnsChannel($userId, $channelId)) {
            $this->permissionCache[$cacheKey] = true;
            return true;
        }

        // Check role-based permissions
        $sql = "SELECT 1
                FROM channel_user_roles cur
                JOIN role_permissions rp ON cur.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE cur.user_id = ?
                AND cur.channel_id = ?
                AND p.name = ?
                AND (cur.expires_at IS NULL OR cur.expires_at > UNIX_TIMESTAMP())";

        $result = $this->db->fetchOne($sql, [$userId, $channelId, $permission]);
        
        $has Permission = $result !== null;
        $this->permissionCache[$cacheKey] = $hasPermission;
        
        return $hasPermission;
    }

    /**
     * Get user's roles for a channel
     */
    public function getUserRoles(int $userId, int $channelId): array
    {
        $sql = "SELECT r.*
                FROM channel_user_roles cur
                JOIN roles r ON cur.role_id = r.id
                WHERE cur.user_id = ?
                AND cur.channel_id = ?
                AND (cur.expires_at IS NULL OR cur.expires_at > UNIX_TIMESTAMP())";

        return $this->db->fetchAll($sql, [$userId, $channelId]);
    }

    /**
     * Grant role to user for a channel
     */
    public function grantRole(int $channelId, int $userId, int $roleId, int $grantedBy, ?int $expiresAt = null): bool
    {
        try {
            $sql = "INSERT INTO channel_user_roles (channel_id, user_id, role_id, granted_by, expires_at)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE granted_by = ?, expires_at = ?";

            $this->db->execute($sql, [
                $channelId,
                $userId,
                $roleId,
                $grantedBy,
                $expiresAt,
                $grantedBy,
                $expiresAt
            ]);

            // Clear cache
            $this->clearPermissionCache($userId, $channelId);

            return true;
        } catch (\Exception $e) {
            error_log("Failed to grant role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke role from user for a channel
     */
    public function revokeRole(int $channelId, int $userId, int $roleId): bool
    {
        try {
            $this->db->execute(
                "DELETE FROM channel_user_roles WHERE channel_id = ? AND user_id = ? AND role_id = ?",
                [$channelId, $userId, $roleId]
            );

            // Clear cache
            $this->clearPermissionCache($userId, $channelId);

            return true;
        } catch (\Exception $e) {
            error_log("Failed to revoke role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get role by name
     */
    public function getRoleByName(string $name): ?array
    {
        return $this->db->fetchOne("SELECT * FROM roles WHERE name = ?", [$name]);
    }

    /**
     * Get all available permissions
     */
    public function getAllPermissions(): array
    {
        return $this->db->fetchAll("SELECT * FROM permissions ORDER BY name");
    }

    /**
     * Get all roles
     */
    public function getAllRoles(): array
    {
        return $this->db->fetchAll("SELECT * FROM roles ORDER BY name");
    }

    /**
     * Check if user is bot admin
     */
    public function isBotAdmin(int $userId): bool
    {
        $adminIds = $this->config->getArray('ADMIN_IDS');
        return in_array($userId, $adminIds);
    }

    /**
     * Clear permission cache for user
     */
    private function clearPermissionCache(int $userId, int $channelId): void
    {
        $prefix = "{$userId}:{$channelId}:";
        foreach (array_keys($this->permissionCache) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset($this->permissionCache[$key]);
            }
        }
    }
}
