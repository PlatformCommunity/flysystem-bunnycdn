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
        $adapter = new BunnyCDNAdapter($client, 'https://pz-url.co.uk');
        $adapter->setTokenAuthKey('test-auth-key');

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $url = $adapter->temporaryUrl('testing.txt', $expiresAt, new Config());

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
}
