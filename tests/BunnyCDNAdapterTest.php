<?php

use BunnyCDN\Storage\BunnyCDNStorage;
use BunnyCDN\Storage\Exceptions\BunnyCDNStorageException;
use League\Flysystem\Config;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\UnreadableFileException;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\Util;

class BunnyCDNAdapterTest extends TestCase
{
    /** @var string Storage Zone Name */
    const STORAGE_ZONE_NAME = 'storage-zone';

    /** @var string API Access Key */
    const API_ACCESS_KEY = '11111111-1111-1111-111111111111-1111-1111';

    /**
     * File Contents for Read / Write testing
     *
     * @var string File Contents
     */
    const TEST_FILE_CONTENTS = 'testing1982';

    /**
     * Mock file structure
     *
     * @var array
     */
    private $exampleFilesAndFolders = [
        [ 'path' => '/' . self::STORAGE_ZONE_NAME . '/testing/', 'is_dir' => true ],
        [ 'path' => '/' . self::STORAGE_ZONE_NAME . '/testing/test.txt', 'is_dir' => false ],
        [ 'path' => '/' . self::STORAGE_ZONE_NAME . '/testing.txt', 'is_dir' => false ],
    ];

    /**
     * @param $storageZoneName
     * @param $apiAccessKey
     * @return BunnyCDNStorage|LegacyMockInterface|MockInterface
     * @throws Exception
     */
    private function getBunnyCDNMockObject($storageZoneName = self::STORAGE_ZONE_NAME, $apiAccessKey = self::API_ACCESS_KEY)
    {
        $mock = Mockery::mock(BunnyCDNStorage::class);

        $mock->shouldReceive('uploadFile')->andReturnUsing(function($localPath, $path) {
            if (count(array_filter($this->exampleFilesAndFolders, static function($file) use ($path) { return $file['path'] == $path; }))){
                if(filesize($localPath) === 0) {
                    throw new BunnyCDNStorageException('Cannot upload file');
                }
            }
            $this->exampleFilesAndFolders[] = [
                'path' => '/' . Util::normalizePath($path),
                'is_dir' => filesize($localPath) === 0
            ];
        });

        $mock->shouldReceive('deleteObject')->andReturnUsing(function($path) {
            $new_filesystem = array_filter($this->exampleFilesAndFolders, static function($file) use ($path) { return $file['path'] !== $path; });

            if($this->exampleFilesAndFolders == $new_filesystem) {
                throw new BunnyCDNStorageException('Could not delete non-existant file');
            }
            $this->exampleFilesAndFolders = array_filter($this->exampleFilesAndFolders, static function($file) use ($path) { return $file['path'] !== $path; });
            return "{status: 200}";
        });

        $mock->shouldReceive('downloadFile')->andReturnUsing(function($path, $localPath) {
            if(count(array_filter($this->exampleFilesAndFolders, static function($file) use ($path) { return $file['path'] === $path; })) === 0) {
                throw new BunnyCDNStorageException('404');
            }
            file_put_contents($localPath, self::TEST_FILE_CONTENTS);
            return true;
        });

        $mock->shouldReceive('getStorageObjects')->andReturnUsing(function($path) {
            return array_map(static function ($file) {
                return self::getExampleFile($file['path'], $file['is_dir']);
            }, $this->exampleFilesAndFolders);
        });

        $mock->storageZoneName = $storageZoneName;
        $mock->apiAccessKey = $apiAccessKey;
        return $mock;

//         return new BunnyCDNStorage('platform-content', '9a41c0ca-1ed4-4512-b9b15ed88c42-a03c-463b');
    }

    /**
     * @param $path
     * @param bool $isDirectory
     * @return stdClass
     * @throws Exception
     */
    private static function getExampleFile($path, $isDirectory = false): \stdClass
    {
        $object = new stdClass();
        $object->Guid = '12345678-1234-1234-1234-123456789876';
        $object->StorageZoneName = self::STORAGE_ZONE_NAME;
        $object->Path = '/' . Util::normalizePath(Util::splitPathIntoDirectoryAndFile($path)['dir'] . '/');
        $object->ObjectName = Util::splitPathIntoDirectoryAndFile($path)['file'];
        $object->Length = random_int(100, 1000);
        $object->LastChanged = '2019-12-08T02:06:16.842';
        $object->ServerId = 36;
        $object->IsDirectory = $isDirectory;
        $object->UserId = '12345678-1234-1234-1234-123456789876';
        $object->DateCreated = '2019-12-08T01:55:55.347';
        $object->StorageZoneId = 12345;
        $object->Checksum = 'D9420CB25DB7C2108E9A5E2F65E8060DFD0DC5B5DCAA8C36A1E1ABC94802F4DA';
        $object->ReplicatedZones = '';
        return $object;
    }

    /**
     * @param $name
     * @return ReflectionMethod
     * @throws ReflectionException
     */
    protected static function getBunnyCDNAdapterMethod($name): \ReflectionMethod
    {
        $class = new ReflectionClass(BunnyCDNAdapter::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_write()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $adapter->delete('testing/test.txt');

        self::assertTrue(
            $adapter->write('testing/test.txt', 'Testing.txt', new Config())
        );

        self::assertTrue(
            $adapter->write('testing/test.txt', self::TEST_FILE_CONTENTS, new Config())
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_has()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertTrue($adapter->has('/testing/test.txt'));
        self::assertTrue($adapter->has('testing/test.txt'));
        self::assertTrue($adapter->has('testing/test.txt/'));
        self::assertTrue($adapter->has('testing'));
        self::assertTrue($adapter->has('testing/'));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_has_slash_prefix()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertTrue($adapter->has('/testing/test.txt'));
        self::assertTrue($adapter->has('//testing/test.txt'));
        self::assertTrue($adapter->has('///testing/test.txt'));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_has_inverse()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertFalse($adapter->has('/not_in_test_files.txt'));
        self::assertFalse($adapter->has('not_a_directory'));
        self::assertFalse($adapter->has('not_a_testing/test.txt'));
    }

    /**
     * @test
     * @throws \League\Flysystem\Exception
     * @throws BunnyCDNStorageException
     * @throws Exception
     */
    public function it_read()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::assertEquals(
            self::TEST_FILE_CONTENTS,
            $adapter->read('/testing/test.txt')['contents']
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_delete()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertTrue($adapter->write('/testing/test.txt', self::TEST_FILE_CONTENTS, new Config()));
        self::assertTrue($adapter->delete('/testing/test.txt'));
        self::assertTrue($adapter->write('/testing/test.txt', self::TEST_FILE_CONTENTS, new Config()));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_delete_dir()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertTrue($adapter->createDir('/testing_for_deletion/',  new Config()));
        self::assertTrue($adapter->deleteDir('/testing_for_deletion/'));
    }

    /**
     * @note This is broken for directories, please only use on files
     *
     * @test
     * @throws \League\Flysystem\Exception
     * @throws BunnyCDNStorageException
     * @throws Exception
     */
    public function it_copy()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::assertTrue(
            $adapter->copy('testing/test.txt', 'testing/test_copied.txt')
        );

        self::assertTrue(
            $adapter->delete('testing/test_copied.txt')
        );
    }

    /**
     * @test
     * @throws BunnyCDNStorageException
     * @throws Exception
     */
    public function it_list_contents() // TODO This is broken
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertIsArray(
            $adapter->listContents('/')
        );
        self::assertIsArray(
            $adapter->listContents('/')[0]
        );
        $this->assertHasMetadataKeys(
            $adapter->listContents('/')[0]
        );
    }

    /**
     * @throws BunnyCDNStorageException
     * @throws FileNotFoundException
     * @test
     * @throws UnreadableFileException
     * @throws Exception
     */
    public function it_get_size()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::assertIsNumeric(
            $adapter->getSize('testing/test.txt')['size']
        );
    }

    /**
     * @throws \BunnyCDN\Storage\Exception
     * @test
     * @throws \League\Flysystem\Exception
     * @throws BunnyCDNStorageException
     * @throws Exception
     */
    public function it_rename()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::assertTrue(
            $adapter->rename('testing/test.txt', 'testing/test_renamed.txt')
        );

        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::assertTrue(
            $adapter->rename('testing/test_renamed.txt', 'testing/test.txt')
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_update()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertTrue(
            $adapter->update('testing/test.txt', self::TEST_FILE_CONTENTS, new Config())
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_create_dir()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertTrue(
            $adapter->createDir('testing_created/', new Config())
        );

        self::assertTrue(
            $adapter->deleteDir('testing_created/')
        );
    }

    /**
     * @test
     * @throws BunnyCDNStorageException
     * @throws FileNotFoundException
     * @throws UnreadableFileException
     * @throws Exception
     */
    public function it_get_timestamp()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::assertIsNumeric(
            $adapter->getTimestamp('testing/test.txt')['timestamp']
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_retrieve_metadata()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        $metadata = $adapter->getMetadata('testing/test.txt');

        $this->assertHasMetadataKeys($metadata);
    }

    private function assertHasMetadataKeys($metadata) {
        self::assertArrayHasKey('type', $metadata);
        self::assertArrayHasKey('dirname', $metadata);
        self::assertArrayHasKey('mimetype', $metadata);
        self::assertArrayHasKey('guid', $metadata);
        self::assertArrayHasKey('path', $metadata);
        self::assertArrayHasKey('object_name', $metadata);
        self::assertArrayHasKey('size', $metadata);
        self::assertArrayHasKey('timestamp', $metadata);
        self::assertArrayHasKey('server_id', $metadata);
        self::assertArrayHasKey('user_id', $metadata);
        self::assertArrayHasKey('last_changed', $metadata);
        self::assertArrayHasKey('date_created', $metadata);
        self::assertArrayHasKey('storage_zone_name', $metadata);
        self::assertArrayHasKey('storage_zone_id', $metadata);
        self::assertArrayHasKey('checksum', $metadata);
        self::assertArrayHasKey('replicated_zones', $metadata);
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_tests_flysystem_compatibility()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $filesystem = new Filesystem($adapter);
        self::assertTrue($filesystem->createDir("test"));
        self::assertTrue($filesystem->deleteDir("test"));
    }

    /**
     * @test
     * @return void
     * @throws \League\Flysystem\FilesystemException
     * @throws \League\Flysystem\FilesystemException
     */
    public function it_get_public_url()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject(), 'https://testing1827129361.b-cdn.net');

        $this->assertEquals('https://testing1827129361.b-cdn.net/testing/test.txt', $adapter->getUrl('/testing/test.txt'));

        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        $this->expectException(RuntimeException::class);

        $adapter->getUrl('/testing/test.txt');
    }
}
