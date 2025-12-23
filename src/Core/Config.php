<?php
declare(strict_types=1);

namespace App\Core;

use Exception;

/**
 * Configuration Manager
 * 
 * Handles loading and accessing environment configuration
 */
class Config
{
    private array $config = [];
    private static ?Config $instance = null;

    private function __construct()
    {
        $this->loadEnvironment();
        $this->loadDefaults();
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadEnvironment(): void
    {
        $envFile = dirname(__DIR__, 2) . '/.env';
        
        if (!file_exists($envFile)) {
            throw new Exception('.env file not found. Please copy .env.example to .env and configure it.');
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                $this->config[$key] = $value;
            }
        }
    }

    private function loadDefaults(): void
    {
        // Set defaults if not provided
        $defaults = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'LOG_LEVEL' => 'info',
            'TIMEZONE' => 'UTC',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'RATE_LIMIT_ACTIONS' => '30',
            'RATE_LIMIT_WINDOW' => '60',
            'SESSION_LIFETIME' => '3600',
            'CACHE_DRIVER' => 'file',
            'ENABLE_SUBSCRIPTIONS' => 'false',
            'ENABLE_AI_FEATURES' => 'false',
            'ENABLE_RSS' => 'true',
            'RSS_CHECK_INTERVAL' => '3600',
            'ENABLE_IP_WHITELIST' => 'false',
            'MAX_CHANNELS_PER_USER' => '100',
            'MAX_POSTS_PER_DAY' => '1000',
            'MAX_SCHEDULED_POSTS' => '500',
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($this->config[$key]) || $this->config[$key] === '') {
                $this->config[$key] = $value;
            }
        }

        // Set timezone
        date_default_timezone_set($this->get('TIMEZONE', 'UTC'));
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);
        if ($value === null) {
            return $default;
        }
        return (int) $value;
    }

    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            return $default;
        }
        
        // Handle comma-separated values
        if (is_string($value)) {
            return array_filter(array_map('trim', explode(',', $value)));
        }
        
        return $default;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    public function isDebug(): bool
    {
        return $this->getBool('APP_DEBUG', false);
    }

    public function isProduction(): bool
    {
        return $this->get('APP_ENV', 'production') === 'production';
    }
}
