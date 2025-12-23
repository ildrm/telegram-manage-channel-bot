<?php
declare(strict_types=1);

namespace App\Modules;

use App\Core\Container;
use App\Interfaces\PluginInterface;
use App\Services\UserService;
use App\Services\AuthorizationService;

/**
 * Auth Module
 * 
 * Handles authentication and authorization
 */
class AuthModule implements PluginInterface
{
    public function register(Container $container): void
    {
        $container->singleton(AuthorizationService::class);
    }

    public function boot(Container $container): void
    {
        // Booted
    }

    public function getListeners(): array
    {
        return [];
    }
}
