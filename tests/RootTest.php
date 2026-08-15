<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;
use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\Test;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;

if (\is_file(__DIR__.'/ClientDI.php')) {
    require_once __DIR__.'/ClientDI.php';
}

class RootTest extends FilesystemAdapterTestCase
{
    /**
     * Root path the adapter is scoped to.
     */
    public const ROOT_PATH = 'root_prefix_12345';

    private static function bunnyCDNClient(): BunnyCDNClient
    {
        global $storage_zone;
        global $api_key;

        if ($storage_zone !== null && $api_key !== null) {
            return new BunnyCDNClient($storage_zone, $api_key);
        }

        return new MockClient('test_storage_zone', '123');
    }

    private static function bunnyCDNAdapter(): BunnyCDNAdapter
    {
        $adapter = new BunnyCDNAdapter(self::bunnyCDNClient(), 'https://example.org.local/assets/', self::ROOT_PATH);
        $adapter->setTokenAuthKey('test-token-auth-key');

        return $adapter;
    }

    public static function createFilesystemAdapter(): FilesystemAdapter
    {
        return self::bunnyCDNAdapter();
    }

    protected function tearDown(): void
    {
        try {
            (new Filesystem(self::bunnyCDNAdapter()))->deleteDirectory('');
        } catch (FilesystemException $e) {
        }
    }

    /**
     * Skipped
     */
    public function setting_visibility(): void
    {
        $this->markTestSkipped('No visibility support is provided for BunnyCDN');
    }

    /**
     * We overwrite the test, because the original tries accessing the url
     */
    #[Test]
    public function generating_a_public_url(): void
    {
        $url = $this->adapter()->publicUrl('path.txt', new Config);

        self::assertEquals('https://example.org.local/assets/'.self::ROOT_PATH.'/path.txt', $url);
    }

    public function overwriting_a_file(): void
    {
        $this->runScenario(function () {
            $this->givenWeHaveAnExistingFile('path.txt', 'contents', ['visibility' => Visibility::PUBLIC]);
            $adapter = $this->adapter();

            $adapter->write('path.txt', 'new contents', new Config(['visibility' => Visibility::PRIVATE]));

            $contents = $adapter->read('path.txt');
            $this->assertEquals('new contents', $contents);
        });
    }

    /**
     * Files written through a root-scoped adapter must not leak outside of the root.
     */
    #[Test]
    public function files_are_scoped_to_the_root(): void
    {
        $this->runScenario(function () {
            $client = self::bunnyCDNClient();
            $adapter = new BunnyCDNAdapter($client, 'https://example.org.local/assets/', self::ROOT_PATH);
            $unscopedAdapter = new BunnyCDNAdapter($client, 'https://example.org.local/assets/');

            $adapter->write('path.txt', 'root-scoped contents', new Config);

            $this->assertTrue($adapter->fileExists('path.txt'));
            $this->assertFalse($unscopedAdapter->fileExists('path.txt'));
            $this->assertTrue($unscopedAdapter->fileExists(self::ROOT_PATH.'/path.txt'));
            $this->assertSame('root-scoped contents', $unscopedAdapter->read(self::ROOT_PATH.'/path.txt'));

            $adapter->delete('path.txt');
            $this->assertFalse($unscopedAdapter->fileExists(self::ROOT_PATH.'/path.txt'));
        });
    }

    /**
     * Temporary URLs must be signed against the root-scoped path.
     */
    #[Test]
    public function generating_a_temporary_url(): void
    {
        $adapter = self::bunnyCDNAdapter();

        $expiresAt = new \DateTimeImmutable('+1 hour');
        $url = $adapter->temporaryUrl('path.txt', $expiresAt, new Config);

        $this->assertStringContainsString(self::ROOT_PATH.'/path.txt?token=', $url);
        $this->assertStringContainsString('&expires=', $url);
    }

    /**
     * The root path must not appear in listed paths (logical paths are root-relative).
     */
    #[Test]
    public function listed_paths_do_not_contain_the_root(): void
    {
        $this->runScenario(function () {
            $adapter = self::bunnyCDNAdapter();
            $adapter->write('folder/file.txt', 'contents', new Config);

            $rootListing = \iterator_to_array($adapter->listContents('', false));
            $this->assertCount(1, $rootListing);
            $this->assertSame('folder', $rootListing[0]['path']);

            $folderListing = \iterator_to_array($adapter->listContents('folder', false));
            $this->assertCount(1, $folderListing);
            $this->assertSame('folder/file.txt', $folderListing[0]['path']);
        });
    }

    /**
     * The root path is normalized: trailing slashes must not break scoping or URL generation.
     */
    #[Test]
    public function root_with_trailing_slash_is_normalized(): void
    {
        $this->runScenario(function () {
            $client = self::bunnyCDNClient();
            $adapter = new BunnyCDNAdapter($client, 'https://example.org.local/assets/', self::ROOT_PATH.'/');

            $adapter->write('folder/path.txt', 'contents', new Config);
            $this->assertTrue($adapter->fileExists('folder/path.txt'));

            $listing = \iterator_to_array($adapter->listContents('folder', false));
            $this->assertCount(1, $listing);
            $this->assertSame('folder/path.txt', $listing[0]['path']);

            $this->assertSame(
                'https://example.org.local/assets/'.self::ROOT_PATH.'/folder/path.txt',
                $adapter->publicUrl('folder/path.txt', new Config)
            );

            $adapter->setTokenAuthKey('test-token-auth-key');
            $url = $adapter->temporaryUrl('folder/path.txt', new \DateTimeImmutable('+1 hour'), new Config);
            $this->assertStringContainsString(self::ROOT_PATH.'/folder/path.txt?token=', $url);
        });
    }

    /**
     * PathPrefixedAdapter on top of a root-scoped adapter keeps working.
     */
    #[Test]
    public function root_can_be_combined_with_path_prefixing(): void
    {
        $adapter = self::bunnyCDNAdapter();
        $prefixed = new PathPrefixedAdapter($adapter, 'additional_prefix');

        $prefixed->write('path.txt', 'contents', new Config);

        $this->assertTrue($adapter->fileExists('additional_prefix/path.txt'));
        $this->assertFalse($adapter->fileExists('path.txt'));

        $this->assertSame('contents', $prefixed->read('path.txt'));
    }
}
