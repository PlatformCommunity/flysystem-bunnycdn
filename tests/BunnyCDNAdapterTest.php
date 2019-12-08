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
        $mock->shouldReceive('downloadFile')->andReturnUsing(static function($path, $localPath) {
            $file = fopen($localPath, 'w+');
            fwrite($file, self::FILE_CONTENTS);
            return true;
        });
        $mock->shouldReceive('getStorageObjects')->andReturn([
            $this->getExampleFile('test')
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
        /** @var BunnyCDNStorage $mockBCDN */
        $mockBCDN = $this->getBunnyCDNMockObject();
        $adapter = new BunnyCDNAdapter($mockBCDN);
        $this->assertTrue(
            $adapter->write('testing/test.txt', 'Testing.txt', new Config())
        );
    }

    public function testHas()
    {

    }

    public function testRead()
    {
        /** @var BunnyCDNStorage $mockBCDN */
        $mockBCDN = $this->getBunnyCDNMockObject();
        $adapter = new BunnyCDNAdapter($mockBCDN);
        $this->assertEquals(
            $adapter->read('testing/test.txt'),
            self::FILE_CONTENTS
        );
    }

    public function testDelete()
    {

    }

    public function testDeleteDir()
    {

    }

    public function testCopy()
    {

    }

    public function testListContents()
    {
        /** @var BunnyCDNStorage $mockBCDN */
        $mockBCDN = $this->getBunnyCDNMockObject('platform-content', '0e273f95-cf35-4881-957ed36fb39f-c82f-457e');
        $adapter = new BunnyCDNAdapter($mockBCDN);
        var_dump($adapter->listContents('/'));
        $this->assertTrue(true);
    }

    public function testGetMetadata()
    {

    }

    public function testGetSize()
    {

    }

    public function testRename()
    {

    }

    public function testUpdate()
    {

    }

    public function testCreateDir()
    {

    }

    public function testGetMimetype()
    {

    }

    public function testGetTimestamp()
    {

    }
}
