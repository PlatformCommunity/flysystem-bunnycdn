<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\Visibility;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;
use Throwable;

class FlysystemTestSuite extends FilesystemAdapterTestCase
{
    public const DEMOURL = 'https://example.org.local';

    protected static bool $isLive = false;

    protected static string $publicUrl = self::DEMOURL;

    public static function setUpBeforeClass(): void
    {
        global $public_url;
        if (isset($public_url)) {
            static::$publicUrl = $public_url;
        }

        static::$publicUrl = rtrim(static::$publicUrl, '/');
    }

    private static function bunnyCDNClient(): BunnyCDNClient
    {
        global $storage_zone;
        global $api_key;
        global $region;

        if ($storage_zone !== null && $api_key !== null) {
            static::$isLive = true;

            return new BunnyCDNClient($storage_zone, $api_key, $region ?? BunnyCDNRegion::DEFAULT);
        }

        return new MockClient('test_storage_zone', '123');
    }

    public static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new BunnyCDNAdapter(self::bunnyCDNClient(), static::$publicUrl);
    }

    /**
     * Skipped
     */
    public function setting_visibility(): void
    {
        $this->markTestSkipped('No visibility support is provided for BunnyCDN');
    }

    public function generating_a_temporary_url(): void
    {
        $this->markTestSkipped('No temporary URL support is provided for BunnyCDN');
    }

    /**
     * We overwrite the test, because the original tries accessing the url
     *
     * @test
     */
    public function generating_a_public_url(): void
    {
        if (self::$isLive && ! \str_starts_with(static::$publicUrl, self::DEMOURL)) {
            parent::generating_a_public_url();

            return;
        }

        $url = $this->adapter()->publicUrl('/path.txt', new Config());

        self::assertEquals(static::$publicUrl.'/path.txt', $url);
    }

    public function test_without_pullzone_url_error_thrown_accessing_url(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('In order to get a visible URL for a BunnyCDN object, you must pass the "pullzone_url" parameter to the BunnyCDNAdapter.');
        $myAdapter = new BunnyCDNAdapter(static::bunnyCDNClient());
        $myAdapter->publicUrl('/path.txt', new Config());
    }

    /**
     * @test
     */
    public function overwriting_a_file(): void
    {
        $this->runScenario(function () {
            $this->givenWeHaveAnExistingFile('path.txt', 'contents', ['visibility' => Visibility::PUBLIC]);
            $adapter = $this->adapter();

            $adapter->write('path.txt', 'new contents', new Config(['visibility' => Visibility::PRIVATE]));

            $contents = $adapter->read('path.txt');
            $this->assertEquals('new contents', $contents);
            // $visibility = $adapter->visibility('path.txt')->visibility();
            // $this->assertEquals(Visibility::PRIVATE, $visibility); // Commented out of this test
        });
    }

    /**
     * @test
     */
    public function fetching_the_mime_type_of_an_svg_file_by_file_name(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.svg',
                '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>',
                new Config()
            );

            $this->assertSame(
                'image/svg+xml',
                $adapter->detectMimeType('source.svg')
            );
        });
    }

    /**
     * Fix issue where `fopen` complains when opening downloaded image file#20
     * https://github.com/PlatformCommunity/flysystem-bunnycdn/pull/20
     *
     * @return void
     *
     * @throws FilesystemException
     * @throws Throwable
     */
    public function test_regression_pr_20()
    {
        $image = base64_decode('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z/C/HgAGgwJ/lK3Q6wAAAABJRU5ErkJggg==');
        $this->givenWeHaveAnExistingFile('path.png', $image);

        $this->runScenario(function () use ($image) {
            $adapter = $this->adapter();

            $stream = $adapter->readStream('path.png');

            $this->assertIsResource($stream);
            $this->assertEquals($image, stream_get_contents($stream));
        });
    }

    /**
     * Github Issue - 28
     * https://github.com/PlatformCommunity/flysystem-bunnycdn/issues/28
     *
     * Issue present where a lot of TypeErrors will appear if you ask for lastModified on Directory (returns FileAttributes)
     *
     * @throws FilesystemException
     */
    public function test_regression_issue_29()
    {
        $client = self::bunnyCDNClient();
        $client->make_directory('/example_folder');

        $adapter = new Filesystem(new BunnyCDNAdapter($client));
        $exception_count = 0;

        try {
            $adapter->fileSize('/example_folder');
        } catch (\Exception $e) {
            $this->assertInstanceOf(UnableToRetrieveMetadata::class, $e);
            $exception_count++;
        }

        try {
            $adapter->mimeType('/example_folder');
        } catch (\Exception $e) {
            $this->assertInstanceOf(UnableToRetrieveMetadata::class, $e);
            $exception_count++;
        }

        try {
            $adapter->lastModified('/example_folder');
        } catch (\Exception $e) {
            $this->assertInstanceOf(UnableToRetrieveMetadata::class, $e);
            $exception_count++;
        }

        // The fact that PHPUnit makes me do this is 🤬
        $this->assertEquals(3, $exception_count);
    }

    /**
     * Github Issue - 39
     * https://github.com/PlatformCommunity/flysystem-bunnycdn/issues/39
     *
     * Can't request file containing json
     *
     * @throws FilesystemException
     */
    public function test_regression_issue_39()
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            $this->adapter()->write('test.json', json_encode(['test' => 123]), new Config([]));

            $response = $adapter->read('/test.json');

            $this->assertIsString($response);
        });
    }
}
