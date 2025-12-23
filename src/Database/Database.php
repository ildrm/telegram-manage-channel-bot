<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use App\Core\Config;
use Exception;

/**
 * Database Connection Manager
 * 
 * Handles MySQL and SQLite connections with automatic migration
 */
class Database
{
    private static ?PDO $pdo = null;
    private Config $config;
    private string $driver;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->driver = $config->get('DB_CONNECTION', 'mysql');
    }

    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        try {
            if ($this->driver === 'mysql') {
                self::$pdo = $this->createMySQLConnection();
            } elseif ($this->driver === 'sqlite') {
                self::$pdo = $this->createSQLiteConnection();
            } else {
                throw new Exception("Unsupported database driver: {$this->driver}");
            }

            // Set PDO attributes
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // Run migrations if needed
            $this->runMigrations();

            return self::$pdo;
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Create MySQL connection
     */
    private function createMySQLConnection(): PDO
    {
        $host = $this->config->get('DB_HOST', '127.0.0.1');
        $port = $this->config->get('DB_PORT', '3306');
        $database = $this->config->get('DB_DATABASE');
        $username = $this->config->get('DB_USERNAME', 'root');
        $password = $this->config->get('DB_PASSWORD', '');

        if (!$database) {
            throw new Exception("DB_DATABASE is required for MySQL connection");
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        return new PDO($dsn, $username, $password, [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
    }

    /**
     * Create SQLite connection
     */
    private function createSQLiteConnection(): PDO
    {
        $dbPath = $this->config->get('DB_PATH', dirname(__DIR__, 2) . '/storage/database.sqlite');
        
        // Ensure storage directory exists
        $storageDir = dirname($dbPath);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $dsn = "sqlite:{$dbPath}";
        return new PDO($dsn);
    }

    /**
     * Run database migrations
     */
    private function runMigrations(): void
    {
        $migration = new Migration(self::$pdo, $this->driver);
        $migration->run();
    }

    /**
     * Execute a query
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch one row
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Fetch single value
     */
    public function fetchColumn(string $sql, array $params = [])
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    /**
     * Execute INSERT and return last insert ID
     */
    public function insert(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return (int) $this->getConnection()->lastInsertId();
    }

    /**
     * Execute UPDATE/DELETE and return affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollBack();
    }

    /**
     * Get database driver
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Check if using MySQL
     */
    public function isMySQL(): bool
    {
        return $this->driver === 'mysql';
    }

    /**
     * Check if using SQLite
     */
    public function isSQLite(): bool
    {
        return $this->driver === 'sqlite';
    }
}
