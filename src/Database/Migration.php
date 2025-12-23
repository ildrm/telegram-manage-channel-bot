<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use Exception;

/**
 * Database Migration System
 * 
 * Handles schema creation and migrations for MySQL and SQLite
 */
class Migration
{
    private PDO $pdo;
    private string $driver;

    public function __construct(PDO $pdo, string $driver)
    {
        $this->pdo = $pdo;
        $this->driver = $driver;
    }

    /**
     * Run all migrations
     */
    public function run(): void
    {
        // Create migrations table if it doesn't exist
        $this->createMigrationsTable();

        // Get list of completed migrations
        $completed = $this->getCompletedMigrations();

        // Run pending migrations
        $migrations = $this->getAllMigrations();

        foreach ($migrations as $name => $migration) {
            if (!in_array($name, $completed)) {
                echo "Running migration: {$name}\n";
                try {
                    $this->pdo->beginTransaction();
                    $migration();
                    $this->markAsCompleted($name);
                    $this->pdo->commit();
                    echo "  ✓ Completed\n";
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    throw new Exception("Migration failed [{$name}]: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable(): void
    {
        if ($this->driver === 'mysql') {
            $sql = "CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                executed_at INTEGER DEFAULT (strftime('%s', 'now'))
            )";
        }

        $this->pdo->exec($sql);
    }

    /**
     * Get completed migrations
     */
    private function getCompletedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM migrations");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Mark migration as completed
     */
    private function markAsCompleted(string $name): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO migrations (migration) VALUES (?)");
        $stmt->execute([$name]);
    }

    /**
     * Get all migrations
     */
    private function getAllMigrations(): array
    {
        return [
            '001_create_users_table' => [$this, 'createUsersTable'],
            '002_create_channels_table' => [$this, 'createChannelsTable'],
            '003_create_channel_owners_table' => [$this, 'createChannelOwnersTable'],
            '004_create_posts_table' => [$this, 'createPostsTable'],
            '005_create_scheduled_table' => [$this, 'createScheduledTable'],
            '006_create_drafts_table' => [$this, 'createDraftsTable'],
            '007_create_rss_feeds_table' => [$this, 'createRSSFeedsTable'],
            '008_create_analytics_tables' => [$this, 'createAnalyticsTables'],
            '009_create_channel_settings_table' => [$this, 'createChannelSettingsTable'],
            '010_create_sessions_table' => [$this, 'createSessionsTable'],
            '011_create_rate_limits_table' => [$this, 'createRateLimitsTable'],
            '012_create_templates_table' => [$this, 'createTemplatesTable'],
            '013_create_backups_table' => [$this, 'createBackupsTable'],
            '014_create_roles_tables' => [$this, 'createRolesTables'],
            '015_create_campaigns_table' => [$this, 'createCampaignsTable'],
            '016_create_approval_tables' => [$this, 'createApprovalTables'],
            '017_create_audit_logs_table' => [$this, 'createAuditLogsTable'],
            '018_create_notifications_table' => [$this, 'createNotificationsTable'],
            '019_create_content_templates_table' => [$this, 'createContentTemplatesTable'],
            '020_create_channel_groups_table' => [$this, 'createChannelGroupsTable'],
        ];
    }

    /**
     * 001: Create users table
     */
    private function createUsersTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE users (
                    user_id BIGINT PRIMARY KEY,
                    username VARCHAR(255),
                    first_name VARCHAR(255),
                    last_name VARCHAR(255),
                    language_code VARCHAR(10) DEFAULT 'en',
                    timezone VARCHAR(50) DEFAULT 'UTC',
                    subscription_tier VARCHAR(50) DEFAULT 'free',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    is_banned TINYINT(1) DEFAULT 0,
                    INDEX idx_username (username),
                    INDEX idx_subscription (subscription_tier)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE users (
                    user_id INTEGER PRIMARY KEY,
                    username TEXT,
                    first_name TEXT,
                    last_name TEXT,
                    language_code TEXT DEFAULT 'en',
                    timezone TEXT DEFAULT 'UTC',
                    subscription_tier TEXT DEFAULT 'free',
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    last_active INTEGER DEFAULT (strftime('%s', 'now')),
                    is_banned INTEGER DEFAULT 0
                )
            ");
        }
    }

    /**
     * 002: Create channels table
     */
    private function createChannelsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE channels (
                    channel_id BIGINT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    username VARCHAR(255),
                    type VARCHAR(50) DEFAULT 'channel',
                    description TEXT,
                    subscriber_count INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_username (username),
                    INDEX idx_type (type),
                    INDEX idx_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE channels (
                    channel_id INTEGER PRIMARY KEY,
                    title TEXT NOT NULL,
                    username TEXT,
                    type TEXT DEFAULT 'channel',
                    description TEXT,
                    subscriber_count INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 1,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    updated_at INTEGER DEFAULT (strftime('%s', 'now'))
                )
            ");
        }
    }

    /**
     * 003: Create channel_owners table
     */
    private function createChannelOwnersTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE channel_owners (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    channel_id BIGINT NOT NULL,
                    user_id BIGINT NOT NULL,
                    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    is_creator TINYINT(1) DEFAULT 0,
                    UNIQUE KEY unique_owner (channel_id, user_id),
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_user_channels (user_id, channel_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE channel_owners (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    added_at INTEGER DEFAULT (strftime('%s', 'now')),
                    is_creator INTEGER DEFAULT 0,
                    UNIQUE(channel_id, user_id),
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * 004: Create posts table
     */
    private function createPostsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE posts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    channel_id BIGINT NOT NULL,
                    message_id BIGINT NOT NULL,
                    user_id BIGINT NOT NULL,
                    campaign_id INT,
                    content_type VARCHAR(50) DEFAULT 'text',
                    content TEXT,
                    media_id VARCHAR(255),
                    buttons JSON,
                    approval_status VARCHAR(50) DEFAULT 'approved',
                    approved_by BIGINT,
                    approved_at TIMESTAMP NULL,
                    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    edit_history JSON,
                    is_deleted TINYINT(1) DEFAULT 0,
                    deleted_at TIMESTAMP NULL,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
                    INDEX idx_channel_posts (channel_id, posted_at),
                    INDEX idx_user_posts (user_id, posted_at),
                    INDEX idx_campaign_posts (campaign_id),
                    INDEX idx_approval_status (approval_status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
 } else {
            $this->pdo->exec("
                CREATE TABLE posts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel_id INTEGER NOT NULL,
                    message_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    campaign_id INTEGER,
                    content_type TEXT DEFAULT 'text',
                    content TEXT,
                    media_id TEXT,
                    buttons TEXT,
                    approval_status TEXT DEFAULT 'approved',
                    approved_by INTEGER,
                    approved_at INTEGER,
                    posted_at INTEGER DEFAULT (strftime('%s', 'now')),
                    edit_history TEXT,
                    is_deleted INTEGER DEFAULT 0,
                    deleted_at INTEGER,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
                )
            ");
        }
    }

    /**
     * 005: Create scheduled table
     */
    private function createScheduledTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE scheduled (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    channel_id BIGINT NOT NULL,
                    user_id BIGINT NOT NULL,
                    campaign_id INT,
                    content_type VARCHAR(50) DEFAULT 'text',
                    content TEXT,
                    media_id VARCHAR(255),
                    buttons JSON,
                    schedule_time TIMESTAMP NOT NULL,
                    timezone VARCHAR(50) DEFAULT 'UTC',
                    recurring JSON,
                    best_time_optimization TINYINT(1) DEFAULT 0,
                    approval_required TINYINT(1) DEFAULT 0,
                    approval_status VARCHAR(50) DEFAULT 'pending',
                    status VARCHAR(50) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
                    INDEX idx_schedule_time (schedule_time, status),
                    INDEX idx_channel_scheduled (channel_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE scheduled (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    campaign_id INTEGER,
                    content_type TEXT DEFAULT 'text',
                    content TEXT,
                    media_id TEXT,
                    buttons TEXT,
                    schedule_time INTEGER NOT NULL,
                    timezone TEXT DEFAULT 'UTC',
                    recurring TEXT,
                    best_time_optimization INTEGER DEFAULT 0,
                    approval_required INTEGER DEFAULT 0,
                    approval_status TEXT DEFAULT 'pending',
                    status TEXT DEFAULT 'pending',
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
                )
            ");
        }
    }

    /**
     * 006: Create drafts table
     */
    private function createDraftsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE drafts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    channel_id BIGINT NOT NULL,
                    user_id BIGINT NOT NULL,
                    name VARCHAR(255),
                    content_type VARCHAR(50) DEFAULT 'text',
                    content TEXT,
                    media_id VARCHAR(255),
                    buttons JSON,
                    version INT DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_user_drafts (user_id, updated_at),
                    INDEX idx_channel_drafts (channel_id, updated_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE drafts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    name TEXT,
                    content_type TEXT DEFAULT 'text',
                    content TEXT,
                    media_id TEXT,
                    buttons TEXT,
                    version INTEGER DEFAULT 1,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    updated_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * 007: Create rss_feeds table
     */
    private function createRSSFeedsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE rss_feeds (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    channel_id BIGINT NOT NULL,
                    user_id BIGINT NOT NULL,
                    feed_url VARCHAR(500) NOT NULL,
                    feed_type VARCHAR(50) DEFAULT 'rss',
                    last_check TIMESTAMP NULL,
                    last_item_id VARCHAR(255),
                    template TEXT,
                    filters JSON,
                    active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_active_feeds (active, last_check)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE rss_feeds (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    feed_url TEXT NOT NULL,
                    feed_type TEXT DEFAULT 'rss',
                    last_check INTEGER DEFAULT 0,
                    last_item_id TEXT,
                    template TEXT,
                    filters TEXT,
                    active INTEGER DEFAULT 1,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * 008: Create analytics tables
     */
    private function createAnalyticsTables(): void
    {
        if ($this->driver === 'mysql') {
            // Post analytics
            $this->pdo->exec("
                CREATE TABLE post_analytics (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    post_id INT NOT NULL,
                    views INT DEFAULT 0,
                    forwards INT DEFAULT 0,
                    reactions JSON,
                    comments_count INT DEFAULT 0,
                    saves INT DEFAULT 0,
                    engagement_rate DECIMAL(5,2) DEFAULT 0.00,
                    measured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                    INDEX idx_post_analytics (post_id, measured_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Channel analytics
            $this->pdo->exec("
                CREATE TABLE channel_analytics (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    channel_id BIGINT NOT NULL,
                    date DATE NOT NULL,
                    subscribers INT DEFAULT 0,
                    new_subscribers INT DEFAULT 0,
                    unsubscribers INT DEFAULT 0,
                    posts_count INT DEFAULT 0,
                    total_views INT DEFAULT 0,
                    avg_engagement DECIMAL(5,2) DEFAULT 0.00,
                    UNIQUE KEY unique_channel_date (channel_id, date),
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    INDEX idx_channel_date (channel_id, date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            // Post analytics
            $this->pdo->exec("
                CREATE TABLE post_analytics (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER NOT NULL,
                    views INTEGER DEFAULT 0,
                    forwards INTEGER DEFAULT 0,
                    reactions TEXT,
                    comments_count INTEGER DEFAULT 0,
                    saves INTEGER DEFAULT 0,
                    engagement_rate REAL DEFAULT 0,
                    measured_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
                )
            ");

            // Channel analytics
            $this->pdo->exec("
                CREATE TABLE channel_analytics (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel_id INTEGER NOT NULL,
                    date TEXT NOT NULL,
                    subscribers INTEGER DEFAULT 0,
                    new_subscribers INTEGER DEFAULT 0,
                    unsubscribers INTEGER DEFAULT 0,
                    posts_count INTEGER DEFAULT 0,
                    total_views INTEGER DEFAULT 0,
                    avg_engagement REAL DEFAULT 0,
                    UNIQUE(channel_id, date),
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE
                )
            ");
        }
    }

    // Continue with remaining tables in next message due to length...
    // I'll create the remaining migration methods
    
    /**
     * 009: Create channel_settings table
     */
    private function createChannelSettingsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE channel_settings (
                    channel_id BIGINT PRIMARY KEY,
                    reactions_enabled TINYINT(1) DEFAULT 1,
                    default_reactions JSON,
                    auto_react TINYINT(1) DEFAULT 0,
                    auto_react_emoji VARCHAR(10),
                    views_enabled TINYINT(1) DEFAULT 1,
                    comments_enabled TINYINT(1) DEFAULT 1,
                    anti_spam_enabled TINYINT(1) DEFAULT 0,
                    spam_blacklist JSON,
                    captcha_enabled TINYINT(1) DEFAULT 0,
                    watermark TEXT,
                    signature TEXT,
                    start_payload_message TEXT,
                    allow_comments_control TINYINT(1) DEFAULT 1,
                    auto_pin_new_posts TINYINT(1) DEFAULT 0,
                    post_approval_required TINYINT(1) DEFAULT 0,
                    default_timezone VARCHAR(50) DEFAULT 'UTC',
                    branding_footer TEXT,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE channel_settings (
                    channel_id INTEGER PRIMARY KEY,
                    reactions_enabled INTEGER DEFAULT 1,
                    default_reactions TEXT,
                    auto_react INTEGER DEFAULT 0,
                    auto_react_emoji TEXT,
                    views_enabled INTEGER DEFAULT 1,
                    comments_enabled INTEGER DEFAULT 1,
                    anti_spam_enabled INTEGER DEFAULT 0,
                    spam_blacklist TEXT,
                    captcha_enabled INTEGER DEFAULT 0,
                    watermark TEXT,
                    signature TEXT,
                    start_payload_message TEXT,
                    allow_comments_control INTEGER DEFAULT 1,
                    auto_pin_new_posts INTEGER DEFAULT 0,
                    post_approval_required INTEGER DEFAULT 0,
                    default_timezone TEXT DEFAULT 'UTC',
                    branding_footer TEXT,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE
                )
            ");
        }
    }


    // Rest of migrations will be in the next part...
    
    /**
     * 010: Create sessions table
     */
    private function createSessionsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE sessions (
                    user_id BIGINT PRIMARY KEY,
                    state VARCHAR(100),
                    data JSON,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE sessions (
                    user_id INTEGER PRIMARY KEY,
                    state TEXT,
                    data TEXT,
                    updated_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * 011: Create rate_limits table
     */
    private function createRateLimitsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE rate_limits (
                    user_id BIGINT PRIMARY KEY,
                    action_count INT DEFAULT 0,
                    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE rate_limits (
                    user_id INTEGER PRIMARY KEY,
                    action_count INTEGER DEFAULT 0,
                    window_start INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");
        }
    }
    
    /**
     * 012: Create templates table
     */
    private function createTemplatesTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE templates (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    content TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_user_templates (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE templates (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    content TEXT NOT NULL,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * 013: Create backups table
     */
    private function createBackupsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE backups (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    channel_id BIGINT NOT NULL,
                    user_id BIGINT NOT NULL,
                    backup_data LONGTEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_channel_backups (channel_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE backups (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    backup_data TEXT NOT NULL,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * 014: Create roles and permissions tables
     */
    private function createRolesTables(): void
    {
        if ($this->driver === 'mysql') {
            // Roles
            $this->pdo->exec("
                CREATE TABLE roles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) UNIQUE NOT NULL,
                    display_name VARCHAR(255) NOT NULL,
                    description TEXT,
                    is_system TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Permissions
            $this->pdo->exec("
                CREATE TABLE permissions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) UNIQUE NOT NULL,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Role-Permission mapping
            $this->pdo->exec("
                CREATE TABLE role_permissions (
                    role_id INT NOT NULL,
                    permission_id INT NOT NULL,
                    PRIMARY KEY (role_id, permission_id),
                    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Channel-User-Role mapping
            $this->pdo->exec("
                CREATE TABLE channel_user_roles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    channel_id BIGINT NOT NULL,
                    user_id BIGINT NOT NULL,
                    role_id INT NOT NULL,
                    granted_by BIGINT,
                    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NULL,
                    UNIQUE KEY unique_channel_user_role (channel_id, user_id, role_id),
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                    FOREIGN KEY (granted_by) REFERENCES users(user_id) ON DELETE SET NULL,
                    INDEX idx_user_roles (user_id),
                    INDEX idx_channel_roles (channel_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Seed default roles and permissions
            $this->seedRolesAndPermissions();
        } else {
            // SQLite versions
            $this->pdo->exec("
                CREATE TABLE roles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT UNIQUE NOT NULL,
                    display_name TEXT NOT NULL,
                    description TEXT,
                    is_system INTEGER DEFAULT 0,
                    created_at INTEGER DEFAULT (strftime('%s', 'now'))
                )
            ");

            $this->pdo->exec("
                CREATE TABLE permissions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT UNIQUE NOT NULL,
                    description TEXT,
                    created_at INTEGER DEFAULT (strftime('%s', 'now'))
                )
            ");

            $this->pdo->exec("
                CREATE TABLE role_permissions (
                    role_id INTEGER NOT NULL,
                    permission_id INTEGER NOT NULL,
                    PRIMARY KEY (role_id, permission_id),
                    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
                )
            ");

            $this->pdo->exec("
                CREATE TABLE channel_user_roles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    role_id INTEGER NOT NULL,
                    granted_by INTEGER,
                    granted_at INTEGER DEFAULT (strftime('%s', 'now')),
                    expires_at INTEGER,
                    UNIQUE(channel_id, user_id, role_id),
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
                )
            ");
            
            $this->seedRolesAndPermissions();
        }
    }

    /**
     * 015: Create campaigns table
     */
    private function createCampaignsTable(): void
    {
        if ($this->driver === 'mysql') {
            // Campaigns
            $this->pdo->exec("
                CREATE TABLE campaigns (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    user_id BIGINT NOT NULL,
                    status VARCHAR(50) DEFAULT 'draft',
                    start_date TIMESTAMP NULL,
                    end_date TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_user_campaigns (user_id, status),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Campaign-Post mapping
            $this->pdo->exec("
                CREATE TABLE campaign_posts (
                    campaign_id INT NOT NULL,
                    post_id INT NOT NULL,
                    PRIMARY KEY (campaign_id, post_id),
                    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
                    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE campaigns (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    description TEXT,
                    user_id INTEGER NOT NULL,
                    status TEXT DEFAULT 'draft',
                    start_date INTEGER,
                    end_date INTEGER,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    updated_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");

            $this->pdo->exec("
                CREATE TABLE campaign_posts (
                    campaign_id INTEGER NOT NULL,
                    post_id INTEGER NOT NULL,
                    PRIMARY KEY (campaign_id, post_id),
                    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
                    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * 016: Create approval workflow tables
     */
    private function createApprovalTables(): void
    {
        if ($this->driver === 'mysql') {
            // Approval workflows
            $this->pdo->exec("
                CREATE TABLE approval_workflows (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    channel_id BIGINT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    required_approvers INT DEFAULT 1,
                    auto_approve_owner TINYINT(1) DEFAULT 1,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    INDEX idx_channel_workflows (channel_id, is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Post approvals
            $this->pdo->exec("
                CREATE TABLE post_approvals (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    post_id INT NOT NULL,
                    workflow_id INT NOT NULL,
                    requested_by BIGINT NOT NULL,
                    status VARCHAR(50) DEFAULT 'pending',
                    approved_count INT DEFAULT 0,
                    rejected_count INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    resolved_at TIMESTAMP NULL,
                    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                    FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id) ON DELETE CASCADE,
                    FOREIGN KEY (requested_by) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_status (status),
                    INDEX idx_workflow_approvals (workflow_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Approval actions
            $this->pdo->exec("
                CREATE TABLE approval_actions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    approval_id INT NOT NULL,
                    user_id BIGINT NOT NULL,
                    action VARCHAR(50) NOT NULL,
                    comment TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (approval_id) REFERENCES post_approvals(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_approval_actions (approval_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE approval_workflows (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    description TEXT,
                    required_approvers INTEGER DEFAULT 1,
                    auto_approve_owner INTEGER DEFAULT 1,
                    is_active INTEGER DEFAULT 1,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE
                )
            ");

            $this->pdo->exec("
                CREATE TABLE post_approvals (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER NOT NULL,
                    workflow_id INTEGER NOT NULL,
                    requested_by INTEGER NOT NULL,
                    status TEXT DEFAULT 'pending',
                    approved_count INTEGER DEFAULT 0,
                    rejected_count INTEGER DEFAULT 0,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    resolved_at INTEGER,
                    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                    FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id) ON DELETE CASCADE,
                    FOREIGN KEY (requested_by) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");

            $this->pdo->exec("
                CREATE TABLE approval_actions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    approval_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    comment TEXT,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (approval_id) REFERENCES post_approvals(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * 017: Create audit_logs table
     */
    private function createAuditLogsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE audit_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT,
                    channel_id BIGINT,
                    action VARCHAR(100) NOT NULL,
                    entity_type VARCHAR(50),
                    entity_id BIGINT,
                    old_value TEXT,
                    new_value TEXT,
                    ip_address VARCHAR(45),
                    user_agent VARCHAR(500),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE,
                    INDEX idx_user_logs (user_id, created_at),
                    INDEX idx_channel_logs (channel_id, created_at),
                    INDEX idx_action (action)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE audit_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    channel_id INTEGER,
                    action TEXT NOT NULL,
                    entity_type TEXT,
                    entity_id INTEGER,
                    old_value TEXT,
                    new_value TEXT,
                    ip_address TEXT,
                    user_agent TEXT,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * 018: Create notifications table
     */
    private function createNotificationsTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    title VARCHAR(255),
                    message TEXT,
                    data JSON,
                    is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    read_at TIMESTAMP NULL,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_user_notifications (user_id, is_read, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE notifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    type TEXT NOT NULL,
                    title TEXT,
                    message TEXT,
                    data TEXT,
                    is_read INTEGER DEFAULT 0,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    read_at INTEGER,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * 019: Create content_templates table
     */
    private function createContentTemplatesTable(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("
                CREATE TABLE content_templates (
                   id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    content_type VARCHAR(50) DEFAULT 'text',
                    content TEXT,
                    media_id VARCHAR(255),
                    buttons JSON,
                    variables JSON,
                    usage_count INT DEFAULT 0,
                    is_shared TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_user_templates (user_id),
                    INDEX idx_shared_templates (is_shared, usage_count)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE content_templates (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    description TEXT,
                    content_type TEXT DEFAULT 'text',
                    content TEXT,
                    media_id TEXT,
                    buttons TEXT,
                    variables TEXT,
                    usage_count INTEGER DEFAULT 0,
                    is_shared INTEGER DEFAULT 0,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * 020: Create channel_groups table
     */
    private function createChannelGroupsTable(): void
    {
        if ($this->driver === 'mysql') {
            // Channel groups
            $this->pdo->exec("
                CREATE TABLE channel_groups (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                    INDEX idx_user_groups (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Channel group members
            $this->pdo->exec("
                CREATE TABLE channel_group_members (
                    group_id INT NOT NULL,
                    channel_id BIGINT NOT NULL,
                    PRIMARY KEY (group_id, channel_id),
                    FOREIGN KEY (group_id) REFERENCES channel_groups(id) ON DELETE CASCADE,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE channel_groups (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    description TEXT,
                    created_at INTEGER DEFAULT (strftime('%s', 'now')),
                    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
                )
            ");

            $this->pdo->exec("
                CREATE TABLE channel_group_members (
                    group_id INTEGER NOT NULL,
                    channel_id INTEGER NOT NULL,
                    PRIMARY KEY (group_id, channel_id),
                    FOREIGN KEY (group_id) REFERENCES channel_groups(id) ON DELETE CASCADE,
                    FOREIGN KEY (channel_id) REFERENCES channels(channel_id) ON DELETE CASCADE
                )
            ");
        }
    }

    /**
     * Seed default roles and permissions
     */
    private function seedRolesAndPermissions(): void
    {
        // Insert default roles
        $roles = [
            ['owner', 'Owner', 'Full control over the channel', 1],
            ['admin', 'Administrator', 'Can manage channel settings and content', 1],
            ['editor', 'Editor', 'Can create and edit posts', 1],
            ['reviewer', 'Reviewer', 'Can review and approve posts', 1],
            ['analyst', 'Analyst', 'Can view analytics only', 1],
        ];

        foreach ($roles as $role) {
            $this->pdo->prepare("
                INSERT INTO roles (name, display_name, description, is_system)
                VALUES (?, ?, ?, ?)
            ")->execute($role);
        }

        // Insert default permissions
        $permissions = [
            ['post.create', 'Create posts'],
            ['post.edit', 'Edit posts'],
            ['post.delete', 'Delete posts'],
            ['post.approve', 'Approve posts'],
            ['schedule.create', 'Create scheduled posts'],
            ['schedule.manage', 'Manage scheduled posts'],
            ['draft.manage', 'Manage drafts'],
            ['analytics.view', 'View analytics'],
            ['settings.manage', 'Manage channel settings'],
            ['members.manage', 'Manage channel members'],
            ['rss.manage', 'Manage RSS feeds'],
            ['campaign.manage', 'Manage campaigns'],
        ];

        $permissionIds = [];
        foreach ($permissions as $permission) {
            $stmt = $this->pdo->prepare("
                INSERT INTO permissions (name, description)
                VALUES (?, ?)
            ");
            $stmt->execute($permission);
            $permissionIds[$permission[0]] = $this->pdo->lastInsertId();
        }

        // Assign permissions to roles
        $rolePermissions = [
            'owner' => array_keys($permissionIds),  // All permissions
            'admin' => ['post.create', 'post.edit', 'post.delete', 'post.approve', 'schedule.create', 'schedule.manage', 'draft.manage', 'analytics.view', 'settings.manage', 'rss.manage', 'campaign.manage'],
            'editor' => ['post.create', 'post.edit', 'schedule.create', 'draft.manage', 'analytics.view'],
            'reviewer' => ['post.approve', 'analytics.view'],
            'analyst' => ['analytics.view'],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $roleId = $this->pdo->query("SELECT id FROM roles WHERE name = '{$roleName}'")->fetchColumn();
            foreach ($perms as $permName) {
                if (isset($permissionIds[$permName])) {
                    $this->pdo->prepare("
                        INSERT INTO role_permissions (role_id, permission_id)
                        VALUES (?, ?)
                    ")->execute([$roleId, $permissionIds[$permName]]);
                }
            }
        }
    }
}

