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
    public function overwriting_a_file(): void { $this->markTestSkipped('Hmmmm'); }
    public function setting_visibility(): void { $this->markTestSkipped('No visibility supported'); }
    public function listing_contents_recursive(): void { $this->markTestSkipped('No recursive supported'); }

    public function test()
    {
        $adapter = $this->adapter();
        $fileExistsBefore = $adapter->fileExists('some/path.txt');
        $adapter->write('some/path.txt', 'contents', new Config());
        $fileExistsAfter = $adapter->fileExists('some/path.txt');

        $this->assertFalse($fileExistsBefore);
        $this->assertTrue($fileExistsAfter);
    }
}
