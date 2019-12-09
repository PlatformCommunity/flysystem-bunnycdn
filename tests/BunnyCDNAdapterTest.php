<?php

use BunnyCDN\Storage\BunnyCDNStorage;
use BunnyCDN\Storage\Exceptions\BunnyCDNStorageException;
use League\Flysystem\Config;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\UnreadableFileException;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;

class BunnyCDNAdapterTest extends TestCase
{
    /** @var string Storage Zone Name */
    const STORAGE_ZONE_NAME = 'storage-zone';

    /** @var string API Access Key */
    const API_ACCESS_KEY = '11111111-1111-1111-111111111111-1111-1111';

    /**
     * File Contents for Read / Write testing
     * @var string File Contents
     */
    const FILE_CONTENTS = 'testing1982';

    /**
     * @param $storageZoneName
     * @param $apiAccessKey
     * @return BunnyCDNStorage|LegacyMockInterface|MockInterface
     */
    private function getBunnyCDNMockObject($storageZoneName = self::STORAGE_ZONE_NAME, $apiAccessKey = self::API_ACCESS_KEY)
    {
        $mock = Mockery::mock(BunnyCDNStorage::class);
        $mock->shouldReceive('uploadFile')->andReturn('');
        $mock->shouldReceive('deleteObject')->andReturn('');
        $mock->shouldReceive('downloadFile')->andReturnUsing(static function($path, $localPath) {
            $file = fopen($localPath, 'w+');
            fwrite($file, self::FILE_CONTENTS);
            return true;
        });
        $mock->shouldReceive('getStorageObjects')->andReturn([
            $this->getExampleFile('directory', true),
            $this->getExampleFile('directory/nested_file123.txt'),
            $this->getExampleFile('test123.txt'),
            $this->getExampleFile('test456.txt'),
            $this->getExampleFile('test789.txt')
        ]);
        $mock->storageZoneName = $storageZoneName;
        $mock->apiAccessKey = $apiAccessKey;
        return $mock;
    }

    /**
     * @param $filename
     * @param bool $isDirectory
     * @return stdClass
     */
    private function getExampleFile($filename, $isDirectory = false)
    {
        $object = new stdClass();
        $object->Guid = '12345678-1234-1234-1234-123456789876';
        $object->StorageZoneName = self::STORAGE_ZONE_NAME;
        $object->Path = '/' . self::STORAGE_ZONE_NAME . '/';
        $object->ObjectName = $filename;
        $object->Length = mt_rand(100, 1000);
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
    protected static function getBunnyCDNAdapterMethod($name) {
        $class = new ReflectionClass(BunnyCDNAdapter::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function it_path_split()
    {
        $reflection = self::getBunnyCDNAdapterMethod('splitPathIntoDirectoryAndFile');
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        $this->assertEquals(
            $reflection->invokeArgs($adapter, ['/testing-dir']),
            [
                'file' => 'testing-dir',
                'dir' => '',
            ]
        );

        $this->assertEquals(
            $reflection->invokeArgs($adapter, ['/testing-dir/']),
            [
                'file' => 'testing-dir',
                'dir' => '',
            ]
        );

        $this->assertEquals(
            $reflection->invokeArgs($adapter, ['/testing-dir/file.txt']),
            [
                'file' => 'file.txt',
                'dir' => '/testing-dir',
            ]
        );

        $this->assertEquals(
            $reflection->invokeArgs($adapter, ['/testing-dir/nested/file.txt']),
            [
                'file' => 'file.txt',
                'dir' => '/testing-dir/nested',
            ]
        );
    }

    /**
     * @test
     *
     */
    public function it_write()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue(
            $adapter->write('testing/test.txt', 'Testing.txt', new Config())
        );
    }

    /**
     * @test
     *
     */
    public function it_has()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue($adapter->has('directory/nested_file123.txt'));
        $this->assertTrue($adapter->has('directory'));
        $this->assertTrue($adapter->has('directory/'));
        $this->assertTrue($adapter->has('directory/nested_file123.txt'));
    }

    /**
     * @test
     *
     */
    public function it_has_slash_prefix()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue($adapter->has('/test123.txt'));
        $this->assertTrue($adapter->has('//test123.txt'));
        $this->assertTrue($adapter->has('///test123.txt'));
    }

    /**
     * @test
     *
     */
    public function it_has_inverse()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertFalse($adapter->has('/not_in_test_files.txt'));
        $this->assertFalse($adapter->has('not_a_directory'));
        $this->assertFalse($adapter->has('not_a_directory/nested_file123.txt'));
    }

    /**
     * @throws \BunnyCDN\Storage\Exception
     * @test
     * @throws \League\Flysystem\Exception
     * @throws BunnyCDNStorageException
     */
    public function it_read()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertEquals(
            $adapter->read('directory/nested_file123.txt')['contents'],
            self::FILE_CONTENTS
        );
    }

    /**
     * @test
     *
     */
    public function it_delete()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue($adapter->delete('nested_file123.txt'));
    }

    /**
     * @test
     *
     */
    public function it_delete_dir()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue($adapter->deleteDir('directory'));
    }

    /**
     * @throws \BunnyCDN\Storage\Exception
     * @test
     * @throws \League\Flysystem\Exception
     * @throws BunnyCDNStorageException
     */
    public function it_copy() // TODO Broken for directories
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue(
            $adapter->copy('directory/nested_file123.txt', 'directory/nested_file456.txt')
        );
    }

    /**
     * @test
     * @throws BunnyCDNStorageException
     */
    public function it_list_contents() // TODO This is broken
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertCount(
            5, $adapter->listContents('/')
        );
    }

    /**
     * @throws BunnyCDNStorageException
     * @throws FileNotFoundException
     * @test
     * @throws UnreadableFileException
     */
    public function it_get_size()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        $this->assertIsNumeric(
            $adapter->getSize('test123.txt')['size']
        );
    }

    /**
     * @throws \BunnyCDN\Storage\Exception
     * @test
     * @throws \League\Flysystem\Exception
     * @throws BunnyCDNStorageException
     */
    public function it_rename()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue(
            $adapter->rename('directory/nested_file123.txt', 'directory/test_copy.txt')
        );
    }

    /**
     * @test
     *
     */
    public function it_update()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue(
            $adapter->update('testing/test.txt', self::FILE_CONTENTS, new Config())
        );
    }

    /**
     * @test
     *
     */
    public function it_create_dir()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue(
            $adapter->createDir('testing/', new Config())
        );
    }

    /**
     * @test
     * @throws BunnyCDNStorageException
     * @throws FileNotFoundException
     * @throws UnreadableFileException
     */
    public function it_get_timestamp()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        $this->assertIsNumeric(
            $adapter->getTimestamp('directory/nested_file123.txt')['timestamp']
        );
    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function it_normalise_path()
    {
        $reflection = self::getBunnyCDNAdapterMethod('startsWith');
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        $this->assertTrue(true);
    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function it_starts_with()
    {
        $reflection = self::getBunnyCDNAdapterMethod('startsWith');
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        $this->assertTrue(
            $reflection->invokeArgs($adapter, ['/test', '/'])
        );

        $this->assertFalse(
            $reflection->invokeArgs($adapter, ['test', '/'])
        );
    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function it_ends_with()
    {
        $reflection = self::getBunnyCDNAdapterMethod('endsWith');
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());

        $this->assertTrue(
            $reflection->invokeArgs($adapter, ['test/', '/'])
        );

        $this->assertFalse(
            $reflection->invokeArgs($adapter, ['test', '/'])
        );

        $this->assertTrue(
            $reflection->invokeArgs($adapter, ['test', ''])
        );
    }

    /**
     * @test
     */
    public function it_can_retrieve_metadata()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $metadata = $adapter->getMetadata('test123.txt');

        $this->assertArrayHasKey('type', $metadata);
        $this->assertArrayHasKey('dirname', $metadata);
        $this->assertArrayHasKey('mimetype', $metadata);
        $this->assertArrayHasKey('guid', $metadata);
        $this->assertArrayHasKey('path', $metadata);
        $this->assertArrayHasKey('object_name', $metadata);
        $this->assertArrayHasKey('size', $metadata);
        $this->assertArrayHasKey('timestamp', $metadata);
        $this->assertArrayHasKey('server_id', $metadata);
        $this->assertArrayHasKey('user_id', $metadata);
        $this->assertArrayHasKey('last_changed', $metadata);
        $this->assertArrayHasKey('date_created', $metadata);
        $this->assertArrayHasKey('storage_zone_name', $metadata);
        $this->assertArrayHasKey('storage_zone_id', $metadata);
        $this->assertArrayHasKey('checksum', $metadata);
        $this->assertArrayHasKey('replicated_zones', $metadata);
    }
}
