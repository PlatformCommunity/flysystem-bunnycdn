<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;


use BunnyCDN\Storage\BunnyCDNStorage;
use Faker\Provider\File;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\Visibility;
use Mockery;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use BunnyCDN\Storage\Exceptions\BunnyCDNStorageException;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\Util;

class FlysystemTestSuite extends FilesystemAdapterTestCase
{
    const STORAGE_ZONE = 'testing_storage_zone';

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());

        $mock_client = Mockery::mock(new BunnyCDNClient(self::STORAGE_ZONE, 'api-key'));

        $mock_client->shouldReceive('list')->andReturnUsing(function($path) use ($filesystem) {
            return $filesystem->listContents($path)->map(function(StorageAttributes $file) {
                return !$file instanceof FileAttributes
                    ? MockClient::example_folder($file->path(), self::STORAGE_ZONE, [])
                    : MockClient::example_file($file->path(), self::STORAGE_ZONE, [
                        'Length' => $file->fileSize()
                    ]);
            })->toArray();
        });

        $mock_client->shouldReceive('download')->andReturnUsing(function($path) use ($filesystem) {
            return $filesystem->read($path);
        });

        $mock_client->shouldReceive('upload')->andReturnUsing(function($path, $contents) use ($filesystem) {
            $filesystem->write($path, $contents);
            return"{status: 200}";
        });

        $mock_client->shouldReceive('make_directory')->andReturnUsing(function($path) use ($filesystem) {
            return $filesystem->createDirectory($path);
        });

        $mock_client->shouldReceive('delete')->andReturnUsing(function($path) use ($filesystem) {
            $filesystem->deleteDirectory($path);
            $filesystem->delete($path);
        });

        return new BunnyCDNAdapter($mock_client);
    }

    /**
     * Skipped
     */
    public function setting_visibility(): void { $this->markTestSkipped('No visibility supported'); }
    public function listing_contents_recursive(): void { $this->markTestSkipped('No recursive supported'); }
    public function fetching_the_mime_type_of_an_svg_file(): void { $this->markTestSkipped('Mimetypes not yet supported'); }


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
}
