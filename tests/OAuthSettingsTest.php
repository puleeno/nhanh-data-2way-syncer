<?php

namespace Puleeno\NhanhVn\Syncer\Tests;

use PHPUnit\Framework\TestCase;
use Puleeno\NhanhVn\Syncer\Admin\Settings;
use Puleeno\NhanhVn\Syncer\Repository\SettingsRepository;
use Puleeno\NhanhVn\Syncer\Service\SettingsService;

class OAuthSettingsTest extends TestCase
{
    private Settings $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_options'] = [];
        $this->controller = new Settings(new SettingsService(new SettingsRepository()));
    }

    /**
     * @return callable
     */
    private function method(string $name): callable
    {
        $ref = new \ReflectionMethod(Settings::class, $name);
        $ref->setAccessible(true);

        return function (Settings $controller, array $args = []) use ($ref) {
            return $ref->invokeArgs($controller, $args);
        };
    }

    public function testGenerateVerifyCodeLengthAndCharset(): void
    {
        $call = $this->method('generate_verify_code');
        $code = $call($this->controller);

        $this->assertSame(32, strlen($code));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{32}$/', $code);
    }

    public function testGenerateVerifyCodeIsRandom(): void
    {
        $call = $this->method('generate_verify_code');
        $this->assertNotSame($call($this->controller), $call($this->controller));
    }

    public function testOauthReturnLinkFallsBackToSettingsPage(): void
    {
        $call = $this->method('oauth_return_link');
        $link = $call($this->controller, [[]]);

        $this->assertSame(admin_url('admin.php?page=nhanh-syncer-settings'), $link);
    }

    public function testOauthReturnLinkPrefersConfiguredValue(): void
    {
        $call = $this->method('oauth_return_link');
        $link = $call($this->controller, [['return_link' => 'https://example.com/callback']]);

        $this->assertSame('https://example.com/callback', $link);
    }

    public function testBuildOauthUrlContainsRequiredParams(): void
    {
        $call = $this->method('build_oauth_url');
        $url = $call($this->controller, ['12345', 'secret', 'https://example.com/cb', 'pos', 'VC']);

        $this->assertStringStartsWith('https://nhanh.vn/oauth?', $url);

        $query = parse_url($url, PHP_URL_QUERY);
        $params = [];
        parse_str($query, $params);

        $this->assertSame('12345', $params['appId']);
        $this->assertSame('pos', $params['service']);
        $this->assertSame('https://example.com/cb', $params['returnLink']);
        $this->assertSame('VC', $params['verifyCode']);
    }

    public function testBuildOauthUrlReturnsEmptyWithoutAppId(): void
    {
        $call = $this->method('build_oauth_url');
        $url = $call($this->controller, ['', 'secret', 'https://example.com/cb', 'pos', 'VC']);

        $this->assertSame('', $url);
    }

    public function testBuildOauthUrlReturnsEmptyWithoutReturnLink(): void
    {
        $call = $this->method('build_oauth_url');
        $url = $call($this->controller, ['12345', 'secret', '', 'pos', 'VC']);

        $this->assertSame('', $url);
    }

    public function testBuildOauthUrlOmitsEmptyVerifyCode(): void
    {
        $call = $this->method('build_oauth_url');
        $url = $call($this->controller, ['12345', 'secret', 'https://example.com/cb', 'pos', null]);

        $this->assertStringNotContainsString('verifyCode', $url);
    }
}