<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use Faker\Provider\File;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\Visibility;
use Mockery;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\Util;
use Throwable;

class FlysystemTestSuite extends FilesystemAdapterTestCase
{
    const STORAGE_ZONE = 'testing_storage_zone';

    /**
     * Used for testing protected methods
     *
     * https://stackoverflow.com/questions/249664/best-practices-to-test-protected-methods-with-phpunit
     */
    public static function callMethod($obj, $name, array $args)
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method->invokeArgs($obj, $args);
    }

    public static function createFilesystemAdapter(): FilesystemAdapter
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());

        $mock_client = Mockery::mock(new BunnyCDNClient(self::STORAGE_ZONE, 'api-key'));


        $mock_client->shouldReceive('list')->andReturnUsing(function ($path) use ($filesystem) {
            return $filesystem->listContents($path)->map(function (StorageAttributes $file) {
                return !$file instanceof FileAttributes
                    ? MockClient::example_folder($file->path(), self::STORAGE_ZONE, [])
                    : MockClient::example_file($file->path(), self::STORAGE_ZONE, [
                        'Length' => $file->fileSize()
                    ]);
            })->toArray();
        });

        $mock_client->shouldReceive('download')->andReturnUsing(function ($path) use ($filesystem) {
            return $filesystem->read($path);
        });

        $mock_client->shouldReceive('stream')->andReturnUsing(function ($path) use ($filesystem) {
            return $filesystem->readStream($path);
        });

        $mock_client->shouldReceive('upload')->andReturnUsing(function ($path, $contents) use ($filesystem) {
            $filesystem->write($path, $contents);
            return"{status: 200}";
        });

        $mock_client->shouldReceive('make_directory')->andReturnUsing(function ($path) use ($filesystem) {
            return $filesystem->createDirectory($path);
        });

        $mock_client->shouldReceive('delete')->andReturnUsing(function ($path) use ($filesystem) {
            $filesystem->deleteDirectory($path);
            $filesystem->delete($path);
        });

        return new BunnyCDNAdapter($mock_client);
    }

    /**
     * Skipped
     */
    public function setting_visibility(): void
    {
        $this->markTestSkipped('No visibility supported');
    }
    public function listing_contents_recursive(): void
    {
        $this->markTestSkipped('No recursive supported');
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
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );

            $this->assertSame(
                'image/svg+xml',
                $adapter->detectMimeType('source.svg')
            );
        });
    }


    /**
     * @test
     */
    public function deep_fetching_a_file(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'this/is/a/long/sub/directory/source.txt',
                'this is test content',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );

            $object = self::callMethod($adapter, 'getObject', ['this/is/a/long/sub/directory/source.txt']);
            $this->assertSame('this/is/a/long/sub/directory/source.txt', $object->path());
        });
    }


    /**
     * Section where Visibility needs to be fixed...
     */

    /**
     * @test
     */
    public function copying_a_file(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );

            $adapter->copy('source.txt', 'destination.txt', new Config());

            $this->assertTrue($adapter->fileExists('source.txt'));
            $this->assertTrue($adapter->fileExists('destination.txt'));
            # Removed as Visibility is not supported in BunnyCDN without
//            $this->assertEquals(Visibility::PUBLIC, $adapter->visibility('destination.txt')->visibility());
            $this->assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    /**
     * @test
     */
    public function copying_a_file_again(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );

            $adapter->copy('source.txt', 'destination.txt', new Config());

            $this->assertTrue($adapter->fileExists('source.txt'));
            $this->assertTrue($adapter->fileExists('destination.txt'));
            # Removed as Visibility is not supported in BunnyCDN without
//            $this->assertEquals(Visibility::PUBLIC, $adapter->visibility('destination.txt')->visibility());
            $this->assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    /**
     * @test
     */
    public function moving_a_file(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );
            $adapter->move('source.txt', 'destination.txt', new Config());
            $this->assertFalse(
                $adapter->fileExists('source.txt'),
                'After moving a file should no longer exist in the original location.'
            );
            $this->assertTrue(
                $adapter->fileExists('destination.txt'),
                'After moving, a file should be present at the new location.'
            );
            # Removed as Visibility is not supported in BunnyCDN without
//            $this->assertEquals(Visibility::PUBLIC, $adapter->visibility('destination.txt')->visibility());
            $this->assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    /**
     * Fix issue where `fopen` complains when opening downloaded image file#20
     * https://github.com/PlatformCommunity/flysystem-bunnycdn/pull/20
     *
     * @return void
     * @throws FilesystemException
     * @throws Throwable
     */
    public function test_regression_pr_20()
    {
        $image = base64_decode("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z/C/HgAGgwJ/lK3Q6wAAAABJRU5ErkJggg==");
        $this->givenWeHaveAnExistingFile('path.png', $image);

        $this->runScenario(function () use ($image) {
            $adapter = $this->adapter();

            $stream = $adapter->readStream('path.png');

            $this->assertIsResource($stream);
            $this->assertEquals($image, stream_get_contents($stream));
        });
    }
}
