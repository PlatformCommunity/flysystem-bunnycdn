<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToRetrieveMetadata;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use Throwable;

class FlysystemTestSuite extends FilesystemAdapterTestCase
{
    /**
     * Storage Zone
     */
    const STORAGE_ZONE = 'testing_storage_zone';

    private static function bunnyCDNClient(): BunnyCDNClient
    {
        return new MockClient(self::STORAGE_ZONE, '123');
//        return new BunnyCDNClient(self::STORAGE_ZONE', 'api-key');
    }

    public static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new BunnyCDNAdapter(self::bunnyCDNClient(), 'https://example.org.local/assets/');
    }

    /**
     * Skipped
     */
    public function setting_visibility(): void
    {
        $this->markTestSkipped('No visibility supported');
    }

    /**
     * We overwrite the test, because the original tries accessing the url
     *
     * @test
     */
    public function generating_a_public_url(): void
    {
        $url = $this->adapter()->publicUrl('/path.txt', new Config());

        self::assertEquals('https://example.org.local/assets/path.txt', $url);
    }

    public function test_without_pullzone_url_error_thrown_accessing_url(): void
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('In order to get a visible URL for a BunnyCDN object, you must pass the "pullzone_url" parameter to the BunnyCDNAdapter.');
        $myAdapter = new BunnyCDNAdapter(static::bunnyCDNClient());
        $myAdapter->publicUrl('/path.txt', new Config());
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
     * Github Issue - 21
     * https://github.com/PlatformCommunity/flysystem-bunnycdn/issues/21
     *
     * Issue present where the date format can come back in either one of the following formats:
     * -    2022-04-10T17:43:49.297
     * -    2022-04-10T17:43:49
     *
     * Pretty sure I'm just going to create a static method called "parse_bunny_date" within the client to handle this.
     *
     * @throws FilesystemException
     */
    public function test_regression_issue_21()
    {
//
//
//        $mock->append(
//            new Response(200, [], json_encode(
//                [
//                    /**
//                     * First with the milliseconds
//                     */
//                    array_merge(
//                        $client::example_file('/example_image.png', self::STORAGE_ZONE),
//                        [
//                            'LastChanged' => date('Y-m-d\TH:i:s.v'),
//                            'DateCreated' => date('Y-m-d\TH:i:s.v'),
//                        ]
//                    ),
//                    /**
//                     * Then without
//                     */
//                    array_merge(
//                        $client::example_file('/example_image.png', self::STORAGE_ZONE),
//                        [
//                            'LastChanged' => date('Y-m-d\TH:i:s'),
//                            'DateCreated' => date('Y-m-d\TH:i:s'),
//                        ]
//                    ),
//                ]
//            ))
//        );
//
//        $adapter = new Filesystem(new BunnyCDNAdapter($client));
//        $response = $adapter->listContents('/', false)->toArray();
//
//        $this->assertIsArray($response);
//        $this->assertCount(2, $response);
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
        $client = new MockClient(self::STORAGE_ZONE, 'api-key');
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
