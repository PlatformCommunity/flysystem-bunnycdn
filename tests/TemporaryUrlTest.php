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
        $adapter->temporaryUrl('testing.text', $expiresAt, new Config());
    }

    public function test_it_can_generate_signing_key()
    {
        $client = new BunnyCDNClient('test', 'test');
        $adapter = new BunnyCDNAdapter($client, 'pz-key');
        $adapter->setTokenAuthKey('test-auth-key');

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $url = $adapter->temporaryUrl('testing.txt', $expiresAt, new Config());

        $this->assertStringContainsString('testing.txt?token=', $url);
        $this->assertStringContainsString('expires='.$expiresAt->getTimestamp(), $url);
    }
}
