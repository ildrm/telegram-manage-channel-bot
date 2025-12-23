<?php

use PHPUnit\Framework\TestCase;
use App\Core\Container;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testSingletonRegistration()
    {
        $this->container->singleton(TestService::class);
        
        $instance1 = $this->container->make(TestService::class);
        $instance2 = $this->container->make(TestService::class);
        
        $this->assertSame($instance1, $instance2);
    }

    public function testMakeInstance()
    {
        $instance = $this->container->make(TestService::class);
        $this->assertInstanceOf(TestService::class, $instance);
    }
}

// Test service class
class TestService
{
    public function test()
    {
        return 'working';
    }
}
