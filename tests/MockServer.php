<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use BunnyCDN\Storage\BunnyCDNStorage;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use Mockery;
use Mockery\MockInterface;
use PlatformCommunity\Flysystem\BunnyCDN\Util;
use stdClass;

class MockServer
{
    public string $storageZoneName;

    public string $apiAccessKey;

    private MockInterface $mock;

    public Filesystem $filesystem;

    public function __construct(string $storageZoneName = 'de', string $apiAccessKey = 'abcdef-123456')
    {
        $this->storageZoneName = $storageZoneName;
        $this->apiAccessKey = $apiAccessKey;

        $this->mock = Mockery::mock(BunnyCDNStorage::class);

        // Create a mock file system that imitates the BunnyCDN Endpoint
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());

        $this->mock->shouldReceive('uploadFile')->andReturnUsing(function($localPath, $path) {
            $contents = file_get_contents($localPath);
            if(strlen($contents) === 0 && str_ends_with($path, '/')) {
                $this->filesystem->createDirectory($path);
            } else {
                $this->filesystem->write($path, $contents);
            }
        });

        $this->mock->shouldReceive('deleteObject')->andReturnUsing(function($path) {
            $this->filesystem->delete($path);
            return"{status: 200}";
        });

        $this->mock->shouldReceive('downloadFile')->andReturnUsing(function($path, $localPath) {
            file_put_contents($localPath, $this->filesystem->read($path));
            return true;
        });

        $this->mock->shouldReceive('getStorageObjects')->andReturnUsing(function($path) {
            return array_map(static function ($object) {
                return self::makeExampleBunnyObject($object);
            }, $this->filesystem->listContents($path, false)->toArray());
        });
    }

    /**
     * @return BunnyCDNStorage
     */
    public function mock(): BunnyCDNStorage
    {
        return $this->mock;
    }

    /**
     * @param StorageAttributes $attributes
     * @return stdClass
     */
    private static function makeExampleBunnyObject(StorageAttributes $attributes): stdClass
    {
        $object = new stdClass();

        # Common
        $object->Guid = '12345678-1234-1234-1234-123456789876';
        $object->UserId = '12345678-1234-1234-1234-123456789876';

        $object->StorageZoneName = 'de';
        $object->Path = Util::normalizePath(Util::splitPathIntoDirectoryAndFile($attributes->path())['dir'] . '/');
        $object->ObjectName = Util::splitPathIntoDirectoryAndFile($attributes->path())['file'];

        $object->LastChanged = date('Y-m-d\TH:i:s:v');
        $object->DateCreated = date('Y-m-d\TH:i:s:v');

        $object->StorageZoneId = 123456;
        $object->ServerId = 302;
        $object->ArrayNumber = 0;

        $object->ContentType = ""; // Never Set

        if($attributes instanceof DirectoryAttributes) {
            # Directory
            $object->IsDirectory = true;
            $object->Length = 0;
            $object->Checksum = null;
            $object->ReplicatedZones = null;
        } elseif($attributes instanceof FileAttributes) {
            # File
            $object->IsDirectory = false;
            $object->Length = rand(0, 1024);
            $object->Checksum = "E3B0C44298FC1C149AFBF4C8996FB92427AE41E4649B934CA495991B7852B855";
            $object->ReplicatedZones = "";
        } else {
            throw new \Exception("Lol what the fuck is this type?");
        }

        return $object;
    }
}