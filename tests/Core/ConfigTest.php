<?php

use PHPUnit\Framework\TestCase;
use App\Core\Config;

class ConfigTest extends TestCase
{
    public function testGetInstance()
    {
        $config = Config::getInstance();
        $this->assertInstanceOf(Config::class, $config);
    }

    public function testSingleton()
    {
        $config1 = Config::getInstance();
        $config2 = Config::getInstance();
        $this->assertSame($config1, $config2);
    }

    public function testGetConfig()
    {
        $config = Config::getInstance();
        // Test with default
        $result = $config->get('NON_EXISTENT_KEY', 'default_value');
        $this->assertEquals('default_value', $result);
    }
}
