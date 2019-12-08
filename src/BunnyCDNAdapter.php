<?php

namespace Sifex\Flysystem\BunnyCDN;

use BunnyCDNStorage;
use BunnyCDNStorageException;
use http\Exception\RuntimeException;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Adapter\Polyfill\StreamedCopyTrait;
use League\Flysystem\Config;
use League\Flysystem\NotSupportedException;
use League\Flysystem\Util;

class BunnyCDNAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;
    use StreamedCopyTrait;

    /**
     * The BunnyCDN Storage Container
     * @var BunnyCDNStorage
     */
    protected $bunnyCDNStorage;

    /**
     * BunnyCDNAdapter constructor.
     * @param BunnyCDNStorage $bunnyCDNStorage
     */
    public function __construct(BunnyCDNStorage $bunnyCDNStorage)
    {
        $this->bunnyCDNStorage = $bunnyCDNStorage;
    }

    /**
     * @inheritDoc
     * @param $path
     * @param $contents
     * @param Config $config
     * @return bool
     */
    public function write($path, $contents, Config $config)
    {
        $temp_pointer = tmpfile();
        fwrite($temp_pointer, $contents);

        /** @var string $url */
        $url = stream_get_meta_data($temp_pointer)['uri'];

        try {
            $this->bunnyCDNStorage->uploadFile(
                $url,
                $this->bunnyCDNStorage->storageZoneName . '/' . $path
            );
        } catch (BunnyCDNStorageException $e) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function writeStream($path, $resource, Config $config)
    {
        throw new NotSupportedException('BunnyCDN does not support steam writing, use ->write() instead');
    }

    /**
     * @inheritDoc
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * @inheritDoc
     */
    public function updateStream($path, $resource, Config $config)
    {
        throw new NotSupportedException('BunnyCDN does not support steam updating, use ->update() instead');
    }

    /**
     * @inheritDoc
     */
    public function rename($path, $newpath)
    {

    }

    /**
     * @inheritDoc
     */
    public function copy($path, $newpath)
    {
        // TODO: Implement copy() method.
    }

    /**
     * @inheritDoc
     */
    public function delete($path)
    {
        // TODO: Implement delete() method.
    }

    /**
     * @inheritDoc
     */
    public function deleteDir($dirname)
    {
        // TODO: Implement deleteDir() method.
    }

    /**
     * @inheritDoc
     */
    public function createDir($dirname, Config $config)
    {
        // TODO: Implement createDir() method.
    }

    /**
     * @inheritDoc
     */
    public function has($path)
    {
        // TODO: Implement has() method.
    }

    /**
     * @inheritDoc
     */
    public function read($path)
    {
        $temp_pointer = tmpfile();

        try {
            $this->bunnyCDNStorage->downloadFile(
                $this->bunnyCDNStorage->storageZoneName . '/' . $path,
                stream_get_meta_data($temp_pointer)['uri']
            );
        } catch (BunnyCDNStorageException $e) {
            return false;
        }

        return file_get_contents(stream_get_meta_data($temp_pointer)['uri']);
    }

    /**
     * @inheritDoc
     */
    public function readStream($path)
    {
        throw new NotSupportedException('BunnyCDN does not support steam reading, use ->read() instead');
    }

    /**
     * @inheritDoc
     */
    public function listContents($directory = '', $recursive = false)
    {
        return $this->bunnyCDNStorage->getStorageObjects(
            $this->bunnyCDNStorage->storageZoneName . '/' . $directory
        );
    }

    /**
     * @inheritDoc
     */
    public function getMetadata($path)
    {
        // TODO: Implement getMetadata() method.
    }

    /**
     * @inheritDoc
     */
    public function getSize($path)
    {
        // TODO: Implement getSize() method.
    }

    /**
     * @inheritDoc
     */
    public function getMimetype($path)
    {
        // TODO: Implement getMimetype() method.
    }

    /**
     * @inheritDoc
     */
    public function getTimestamp($path)
    {
        // TODO: Implement getTimestamp() method.
    }
}
