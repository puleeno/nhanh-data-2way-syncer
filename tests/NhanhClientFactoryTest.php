<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Service\NhanhClientFactory;

class NhanhClientFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_options'] = [];
    }

    public function testMakeReturnsNullWhenMissingCredentials(): void
    {
        $settings = ['app_id' => '', 'business_id' => '', 'access_token' => ''];
        $factory = new NhanhClientFactory($settings);
        $client = $factory->make();
        $this->assertNull($client);
    }

    public function testMakeReturnsNullWhenMissingAppId(): void
    {
        $settings = ['app_id' => '', 'business_id' => '123', 'access_token' => 'token'];
        $factory = new NhanhClientFactory($settings);
        $client = $factory->make();
        $this->assertNull($client);
    }

    public function testMakeReturnsNullWhenMissingBusinessId(): void
    {
        $settings = ['app_id' => '123', 'business_id' => '', 'access_token' => 'token'];
        $factory = new NhanhClientFactory($settings);
        $client = $factory->make();
        $this->assertNull($client);
    }

    public function testMakeReturnsNullWhenMissingAccessToken(): void
    {
        $settings = ['app_id' => '123', 'business_id' => '123', 'access_token' => ''];
        $factory = new NhanhClientFactory($settings);
        $client = $factory->make();
        $this->assertNull($client);
    }
}