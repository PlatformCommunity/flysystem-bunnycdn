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
    const STORAGE_ZONE_NAME = 'test12391248982';

    /** @var string API Access Key */
    const API_ACCESS_KEY = 'f053a6e4-5b85-46a3-922fc6664a1e-cb04-47e0';

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
                throw new BunnyCDNStorageException('Cannot upload file');
            }
            $this->exampleFilesAndFolders[] = [
                'path' => '/' . Util::normalizePath($path),
                'is_dir' => false
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

        return new BunnyCDNStorage('', '', 'sg');
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
        try {
            self::assertNull($adapter->delete('testing/test.txt'));
        } catch (Exception $exception) {

        }
        $this->assertNull($adapter->write('testing/test.txt', self::TEST_FILE_CONTENTS, new Config()));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_has()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertTrue($adapter->fileExists('/testing/test.txt'));
        self::assertTrue($adapter->fileExists('testing/test.txt'));
        self::assertTrue($adapter->fileExists('testing/test.txt/'));
        self::assertTrue($adapter->fileExists('testing'));
        self::assertTrue($adapter->fileExists('testing/'));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_has_slash_prefix()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertTrue($adapter->fileExists('/testing/test.txt'));
        self::assertTrue($adapter->fileExists('//testing/test.txt'));
        self::assertTrue($adapter->fileExists('///testing/test.txt'));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_has_inverse()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertFalse($adapter->fileExists('/not_in_test_files.txt'));
        self::assertFalse($adapter->fileExists('not_a_directory'));
        self::assertFalse($adapter->fileExists('not_a_testing/test.txt'));
    }

    /**
     * @test
     * @throws \League\Flysystem\Exception

     * @throws Exception
     */
    public function it_read()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        
        self::assertEquals(
            self::TEST_FILE_CONTENTS,
            $adapter->read('/testing/test.txt')
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_delete()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertNull($adapter->write('/testing/test2.txt', self::TEST_FILE_CONTENTS, new Config()));
        self::assertNull($adapter->delete('/testing/test2.txt'));
        self::assertNull($adapter->write('/testing/test2.txt', self::TEST_FILE_CONTENTS, new Config()));

        self::expectException(\League\Flysystem\UnableToDeleteFile::class);
        self::assertNull($adapter->delete('/example_file_that_doesnt_exist'));
    }

    /**
     * @test
     * @return void
     * @throws \League\Flysystem\FilesystemException
     */
    public function it_delete_dir()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertNull($adapter->createDirectory('/testing_for_deletion/',  new Config()));
        self::assertNull($adapter->deleteDirectory('/testing_for_deletion/'));

        self::expectException(\League\Flysystem\UnableToDeleteDirectory::class);
        self::assertNull($adapter->deleteDirectory('/testing_for_deletion/'));
    }

    /**
     * @note This is broken for directories, please only use on files
     *
     * @test
     * @throws \League\Flysystem\Exception
     * @throws Exception
     */
    public function it_copy()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::assertNull(
            $adapter->copy('testing/test.txt', 'testing/test_copied.txt', new Config())
        );

        self::assertNull(
            $adapter->delete('testing/test_copied.txt')
        );

        self::expectException(\League\Flysystem\UnableToCopyFile::class);
        $adapter->copy('/example_file_that_doesnt_exist', '/example_file_that_also_doesnt_exist', new Config());
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_list_contents()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        self::assertIsArray(
            $adapter->listContents('/')
        );
        self::assertIsArray(
            $adapter->listContents('/')[0]
        );
    }

    /**
     * @test
     * @return void
     * @throws Exception
     * @throws Exception
     */
    public function it_get_size()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::assertIsNumeric(
            $adapter->fileSize('testing/test.txt')->fileSize()
        );
    }

    /**
     * @test
     * @return void
     * @throws \League\Flysystem\FilesystemException
     */
    public function it_rename()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::assertNull(
            $adapter->move('testing/test.txt', 'testing/test_renamed.txt', new Config())
        );

        self::assertNull(
            $adapter->move('testing/test_renamed.txt', 'testing/test.txt', new Config())
        );
    }

    /**
     * @test
     * @throws Exception
     * @throws \League\Flysystem\FilesystemException
     */
    public function it_create_dir()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::assertNull(
            $adapter->createDirectory('testing_created/', new Config())
        );

        self::expectException(\League\Flysystem\UnableToCreateDirectory::class);

        self::assertNull(
            $adapter->createDirectory('testing_created/', new Config())
        );

        self::assertNull(
            $adapter->deleteDirectory('testing_created/')
        );
    }

    /**
     * @test
     * @return void
     * @throws Exception
     */
    public function it_get_timestamp()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::assertIsNumeric(
            $adapter->lastModified('testing/test.txt')->lastModified()
        );
    }

    /**
     * @test
     * @return void
     * @throws \League\Flysystem\FilesystemException
     * @throws \League\Flysystem\FilesystemException
     */
    public function it_get_visibility()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::expectException(\League\Flysystem\UnableToRetrieveMetadata::class);

        $adapter->visibility('testing/test.txt');
    }

    /**
     * @test
     * @return void
     * @throws \League\Flysystem\FilesystemException
     * @throws \League\Flysystem\FilesystemException
     */
    public function it_set_visibility()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        self::expectException(\League\Flysystem\UnableToSetVisibility::class);

        $adapter->setVisibility('testing/test.txt', 878);
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
