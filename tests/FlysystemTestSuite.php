<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;


use BunnyCDN\Storage\BunnyCDNStorage;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use BunnyCDN\Storage\Exceptions\BunnyCDNStorageException;
use PlatformCommunity\Flysystem\BunnyCDN\Util;

class FlysystemTestSuite extends FilesystemAdapterTestCase
{
    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new BunnyCDNAdapter((new MockServer('testing'))->mock());
    }

    /**
     * Skipped
     */
    public function overwriting_a_file(): void { $this->markTestSkipped('Hmmmm'); }
    public function setVisibility() { $this->markTestSkipped('No visibility supported'); }
}
