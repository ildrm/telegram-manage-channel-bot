<?php
declare(strict_types=1);

namespace App\Interfaces;

use App\Core\Container;

/**
 * Plugin Interface
 * 
 * All modules must implement this interface
 */
interface PluginInterface
{
    /**
     * Register services in the container
     */
    public function register(Container $container): void;

    /**
     * Boot the plugin
     */
    public function boot(Container $container): void;

    /**
     * Get event listeners
     * 
     * @return array Event name => method name
     */
    public function getListeners(): array;
}
