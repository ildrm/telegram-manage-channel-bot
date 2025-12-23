<?php
declare(strict_types=1);

namespace App\Core;

use Exception;
use ReflectionClass;

/**
 * Simple Dependency Injection Container
 * 
 * Manages service instantiation and dependency resolution
 */
class Container
{
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];

    /**
     * Bind a service to the container
     */
    public function bind(string $abstract, $concrete = null, bool $singleton = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];
    }

    /**
     * Bind a singleton service
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as a singleton
     */
    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
        $this->singletons[$abstract] = true;
    }

    /**
     * Resolve a service from the container
     */
    public function make(string $abstract, array $parameters = [])
    {
        // Return existing instance if singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? null;
        
        if (!$binding) {
            // Try to auto-resolve
            return $this->resolve($abstract, $parameters);
        }

        $concrete = $binding['concrete'];

        // If concrete is a closure, call it
        if ($concrete instanceof \Closure) {
            $object = $concrete($this, $parameters);
        } else {
            $object = $this->resolve($concrete, $parameters);
        }

        // Store singleton instance
        if ($binding['singleton']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Resolve a class and its dependencies
     */
    private function resolve(string $concrete, array $parameters = [])
    {
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new Exception("Cannot resolve [{$concrete}]: {$e->getMessage()}");
        }

        if (!$reflector->isInstantiable()) {
            throw new Exception("Class [{$concrete}] is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();
        $instances = $this->resolveDependencies($dependencies, $parameters);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Resolve constructor dependencies
     */
    private function resolveDependencies(array $dependencies, array $parameters = []): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            // Check if parameter was provided
            if (isset($parameters[$dependency->getName()])) {
                $results[] = $parameters[$dependency->getName()];
                continue;
            }

            // Try to resolve type-hinted dependency
            $type = $dependency->getType();
            
            if ($type && !$type->isBuiltin()) {
                $results[] = $this->make($type->getName());
                continue;
            }

            // Use default value if available
            if ($dependency->isDefaultValueAvailable()) {
                $results[] = $dependency->getDefaultValue();
                continue;
            }

            throw new Exception("Cannot resolve dependency [{$dependency->getName()}]");
        }

        return $results;
    }

    /**
     * Check if service is bound
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Call a method with dependency injection
     */
    public function call($callback, array $parameters = [])
    {
        if (is_string($callback) && strpos($callback, '@') !== false) {
            $callback = explode('@', $callback);
            $callback[0] = $this->make($callback[0]);
        }

        if (is_array($callback)) {
            $reflector = new \ReflectionMethod($callback[0], $callback[1]);
        } elseif ($callback instanceof \Closure) {
            $reflector = new \ReflectionFunction($callback);
        } else {
            throw new Exception("Invalid callback");
        }

        $dependencies = $reflector->getParameters();
        $instances = $this->resolveDependencies($dependencies, $parameters);

        return call_user_func_array($callback, $instances);
    }
}
