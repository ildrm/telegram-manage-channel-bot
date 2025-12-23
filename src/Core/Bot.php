<?php
declare(strict_types=1);

namespace App\Core;

use App\Database\Database;
use App\Telegram\Client;
use App\Telegram\UpdateHandler;
use Exception;

/**
 * Main Bot Class
 * 
 * Bootstraps and runs the Telegram bot
 */
class Bot
{
    private Container $container;
    private PluginManager $pluginManager;
    private Config $config;
    private Database $database;
    private Client $telegram;
    private UpdateHandler $updateHandler;

    public function __construct()
    {
        // Initialize core components
        $this->config = Config::getInstance();
        $this->container = new Container();
        $this->pluginManager = new PluginManager($this->container);

        // Register core services
        $this->registerCoreServices();

        // Initialize services
        $this->database = $this->container->make(Database::class);
        $this->telegram = $this->container->make(Client::class);
        $this->updateHandler = $this->container->make(UpdateHandler::class);

        // Register all modules
        $this->registerModules();

        // Boot all modules
        $this->pluginManager->boot();
    }

    /**
     * Register core services in the container
     */
    private function registerCoreServices(): void
    {
        // Config (singleton)
        $this->container->instance(Config::class, $this->config);

        // Container itself
        $this->container->instance(Container::class, $this->container);

        // Plugin Manager
        $this->container->instance(PluginManager::class, $this->pluginManager);

        // Database (singleton)
        $this->container->singleton(Database::class);

        // Telegram Client (singleton)
        $this->container->singleton(Client::class);

        // Update Handler
        $this->container->singleton(UpdateHandler::class);

        // Services will be registered by modules
    }

    /**
     * Register all modules/plugins
     */
    private function registerModules(): void
    {
        // Core modules
        $this->pluginManager->register(\App\Modules\CoreModule::class);
        $this->pluginManager->register(\App\Modules\AuthModule::class);
        
        // Content management
        $this->pluginManager->register(\App\Modules\ContentModule::class);
        $this->pluginManager->register(\App\Modules\DraftModule::class);
        
        // Scheduling
        $this->pluginManager->register(\App\Modules\SchedulingModule::class);
        
        // Analytics
        $this->pluginManager->register(\App\Modules\AnalyticsModule::class);
        
        // Channel management
        $this->pluginManager->register(\App\Modules\ChannelModule::class);
        $this->pluginManager->register(\App\Modules\SettingsModule::class);
        
        // Roles & Permissions
        $this->pluginManager->register(\App\Modules\RolesModule::class);
        
        // Campaigns
        $this->pluginManager->register(\App\Modules\CampaignModule::class);
        
        // Automation
        $this->pluginManager->register(\App\Modules\AutomationModule::class);
        $this->pluginManager->register(\App\Modules\RSSModule::class);
        
        // Approvals
        $this->pluginManager->register(\App\Modules\ApprovalModule::class);
        
        // Notifications
        $this->pluginManager->register(\App\Modules\NotificationModule::class);
        
        // Multi-channel
        $this->pluginManager->register(\App\Modules\MultiChannelModule::class);
        
        // Backup
        $this->pluginManager->register(\App\Modules\BackupModule::class);
    }

    /**
     * Process incoming webhook update
     */
    public function processUpdate(array $update): void
    {
        try {
            $this->updateHandler->handle($update);
        } catch (Exception $e) {
            error_log("Error processing update: " . $e->getMessage());
            
            if ($this->config->isDebug()) {
                error_log("Update data: " . json_encode($update));
                error_log("Stack trace: " . $e->getTraceAsString());
            }
        }
    }

    /**
     * Set webhook
     */
    public function setWebhook(?string $url = null): bool
    {
        $webhookUrl = $url ?? $this->config->get('WEBHOOK_URL');
        
        if (!$webhookUrl) {
            throw new Exception('WEBHOOK_URL is required');
        }

        $result = $this->telegram->setWebhook($webhookUrl, [
            'allowed_updates' => [
                'message',
                'edited_message',
                'channel_post',
                'edited_channel_post',
                'callback_query',
                'inline_query',
                'my_chat_member',
                'chat_member',
                'poll',
                'poll_answer'
            ]
        ]);

        return $result !== null;
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(): bool
    {
        $result = $this->telegram->deleteWebhook();
        return $result !== null;
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): ?array
    {
        return $this->telegram->getWebhookInfo();
    }

    /**
     * Get bot information
     */
    public function getMe(): ?array
    {
        return $this->telegram->getMe();
    }

    /**
     * Get container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Get database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Get Telegram client
     */
    public function getTelegram(): Client
    {
        return $this->telegram;
    }

    /**
     * Get plugin manager
     */
    public function getPluginManager(): PluginManager
    {
        return $this->pluginManager;
    }
}
