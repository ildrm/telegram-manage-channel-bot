<?php
declare(strict_types=1);

namespace App\Core;

use App\Interfaces\PluginInterface;

/**
 * Plugin Manager
 * 
 * Manages module loading, registration, and event dispatching
 */
class PluginManager
{
    private Container $container;
    private array $plugins = [];
    private array $listeners = [];
   private bool $booted = false;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register a plugin
     */
    public function register(string $pluginClass): void
    {
        if (isset($this->plugins[$pluginClass])) {
            return;
        }

        if (!class_exists($pluginClass)) {
            throw new \Exception("Plugin class [{$pluginClass}] not found");
        }

        $plugin = new $pluginClass();

        if (!$plugin instanceof PluginInterface) {
            throw new \Exception("Plugin [{$pluginClass}] must implement PluginInterface");
        }

        // Register plugin services
        $plugin->register($this->container);

        // Store plugin
        $this->plugins[$pluginClass] = $plugin;

        // Register event listeners
        $listeners = $plugin->getListeners();
        foreach ($listeners as $event => $method) {
            $this->addListener($event, [$plugin, $method]);
        }
    }

    /**
     * Boot all registered plugins
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->plugins as $plugin) {
            $plugin->boot($this->container);
        }

        $this->booted = true;
    }

    /**
     * Add an event listener
     */
    public function addListener(string $event, callable $callback): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $callback;
    }

    /**
     * Dispatch an event to all listeners
     */
    public function dispatch(string $event, ...$args): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $listener) {
            try {
                call_user_func_array($listener, $args);
            } catch (\Exception $e) {
                // Log error but continue processing other listeners
                error_log("Error in event listener for [{$event}]: " . $e->getMessage());
            }
        }
    }

    /**
     * Get all registered plugins
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * Check if plugin is registered
     */
    public function hasPlugin(string $pluginClass): bool
    {
        return isset($this->plugins[$pluginClass]);
    }
}
