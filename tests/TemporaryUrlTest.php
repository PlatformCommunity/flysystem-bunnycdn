<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use League\Flysystem\Config;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use PHPUnit\Framework\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;

class TemporaryUrlTest extends TestCase
{
    public function test_temporary_url_throws_exception_if_not_configured()
    {
        $this->expectException(UnableToGenerateTemporaryUrl::class);
        $this->expectExceptionMessage('you must call the `setTokenAuthKey`');

        $client = new BunnyCDNClient('test', 'test');
        $adapter = new BunnyCDNAdapter($client, 'pz-key');

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $adapter->temporaryUrl('testing.text', $expiresAt, new Config);
    }

    public function test_it_can_generate_signing_key()
    {
        $client = new BunnyCDNClient('test', 'test');
        $adapter = new BunnyCDNAdapter($client, 'https://pz-url.co.uk');
        $adapter->setTokenAuthKey('test-auth-key');

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $url = $adapter->temporaryUrl('testing.txt', $expiresAt, new Config);

        $this->assertStringContainsString('https://pz-url.co.uk/testing.txt?token=', $url);
        $this->assertStringContainsString('expires='.$expiresAt->getTimestamp(), $url);
    }

    public function test_it_will_accept_query_params()
    {
        $client = new BunnyCDNClient('test', 'test');
        $adapter = new BunnyCDNAdapter($client, 'https://pz-url.co.uk');
        $adapter->setTokenAuthKey('test-auth-key');

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $url = $adapter->temporaryUrl('testing.txt', $expiresAt, new Config([
            'withQueryParams' => [
                'testParam' => 'testValue',
            ],
        ]));

        $this->assertStringContainsString('https://pz-url.co.uk/testing.txt?token=', $url);
        $this->assertStringContainsString('expires='.$expiresAt->getTimestamp(), $url);
        $this->assertStringContainsString('testParam=testValue', $url);
    }

    public function test_it_can_generate_temporary_url_via_laravel_compatible_method(): void
    {
        $client = new BunnyCDNClient('test', 'test');
        $adapter = new BunnyCDNAdapter($client, 'https://pz-url.co.uk');
        $adapter->setTokenAuthKey('test-auth-key');

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $url = $adapter->getTemporaryUrl('testing.txt', $expiresAt, []);

        $this->assertSame(
            $adapter->temporaryUrl('testing.txt', $expiresAt, new Config),
            $url
        );
    }

    public function test_it_can_generate_temporary_url_with_minutes_as_expiration(): void
    {
        $client = new BunnyCDNClient('test', 'test');
        $adapter = new BunnyCDNAdapter($client, 'https://pz-url.co.uk');
        $adapter->setTokenAuthKey('test-auth-key');

        $expiresIn = 60;
        $url = $adapter->getTemporaryUrl('testing.txt', $expiresIn, []);

        $this->assertStringContainsString('https://pz-url.co.uk/testing.txt?token=', $url);
        $this->assertStringContainsString('expires='.(time() + ($expiresIn * 60)), $url);
    }

    public function test_it_can_generate_temporary_url_with_options_as_query_params(): void
    {
        $client = new BunnyCDNClient('test', 'test');
        $adapter = new BunnyCDNAdapter($client, 'https://pz-url.co.uk');
        $adapter->setTokenAuthKey('test-auth-key');

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $url = $adapter->getTemporaryUrl('testing.txt', $expiresAt, [
            'testParam' => 'testValue',
        ]);

        $this->assertStringContainsString('https://pz-url.co.uk/testing.txt?token=', $url);
        $this->assertStringContainsString('expires='.$expiresAt->getTimestamp(), $url);
        $this->assertStringContainsString('testParam=testValue', $url);
    }

    public function test_temporary_url_with_root_scopes_the_signed_path(): void
    {
        $client = new BunnyCDNClient('test', 'test');
        $adapter = new BunnyCDNAdapter($client, 'https://pz-url.co.uk', 'assets');
        $adapter->setTokenAuthKey('test-auth-key');

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $url = $adapter->temporaryUrl('testing.txt', $expiresAt, new Config);

        $this->assertStringContainsString('https://pz-url.co.uk/assets/testing.txt?token=', $url);
        $this->assertStringContainsString('expires='.$expiresAt->getTimestamp(), $url);
    }

    public function test_temporary_url_with_full_url_path_does_not_apply_root(): void
    {
        $client = new BunnyCDNClient('test', 'test');
        $adapter = new BunnyCDNAdapter($client, 'https://pz-url.co.uk', 'assets');
        $adapter->setTokenAuthKey('test-auth-key');

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $url = $adapter->temporaryUrl('https://cdn.example.org/path/file.txt?download=file.txt', $expiresAt, new Config);

        $this->assertStringContainsString('https://cdn.example.org/path/file.txt', $url);
        $this->assertStringContainsString('token=', $url);
        $this->assertStringNotContainsString('/assets', $url);
        $this->assertStringContainsString('expires='.$expiresAt->getTimestamp(), $url);
    }
}
