<?php

use League\Flysystem\Config;
use PHPUnit\Framework\TestCase;
use Sifex\Flysystem\BunnyCDN\BunnyCDNAdapter;

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
     * @return BunnyCDNStorage|\Mockery\LegacyMockInterface|\Mockery\MockInterface
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

    public function testWrite()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue(
            $adapter->write('testing/test.txt', 'Testing.txt', new Config())
        );
    }

    public function testHas()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue($adapter->has('test123.txt'));
    }

    public function testHas_Directory()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue($adapter->has('directory'));
    }

    public function testHas_Directory_Nested()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue($adapter->has('directory/nested_file123.txt'));
    }

    public function testHas_Slash_Prefix()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue($adapter->has('/test123.txt'));
    }

    public function testHas_Double_Slash_Prefix()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue($adapter->has('//test123.txt'));
    }

    public function testHas_Inverse()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertFalse($adapter->has('/not_in_test_files.txt'));
    }

    public function testHas_Inverse_Directory()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertFalse($adapter->has('not_a_directory'));
    }

    public function testHas_Inverse_Directory_Nested()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertFalse($adapter->has('not_a_directory/nested_file123.txt'));
    }

    public function testRead()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertEquals(
            $adapter->read('testing/test.txt'),
            self::FILE_CONTENTS
        );
    }

    public function testDelete()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue($adapter->delete('nested_file123.txt'));
    }

    public function testDeleteDir()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue($adapter->delete('directory'));
    }

    public function testCopy()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue(
            $adapter->copy('directory/test.txt', 'directory/test_copy.txt')
        );
    }

    public function testListContents()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertCount(
            5, $adapter->listContents('/')
        );
    }

    public function testGetSize()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertIsNumeric(
            $adapter->getSize('test123.txt')
        );
    }

    public function testRename()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue(
            $adapter->rename('directory/test.txt', 'directory/test_copy.txt')
        );
    }

    public function testUpdate()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue(
            $adapter->update('testing/test.txt', 'Testing.txt', new Config())
        );
    }

    public function testCreateDir()
    {
        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertTrue(
            $adapter->createDir('testing/', new Config())
        );
    }

    public function testGetTimestamp()
    {

        $adapter = new BunnyCDNAdapter($this->getBunnyCDNMockObject());
        $this->assertIsNumeric(
            $adapter->getTimestamp('test123.txt')
        );
    }
}
