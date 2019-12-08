<?php

namespace Sifex\Flysystem\BunnyCDN;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Adapter\Polyfill\StreamedCopyTrait;
use League\Flysystem\Config;
use League\Flysystem\Util;

class BunnyCDNAdapter extends AbstractAdapter
{
    /**
     * @inheritDoc
     */
    public function write($path, $contents, Config $config)
    {
        // TODO: Implement write() method.
    }

    /**
     * @inheritDoc
     */
    public function writeStream($path, $resource, Config $config)
    {
        // TODO: Implement writeStream() method.
    }

    /**
     * @inheritDoc
     */
    public function update($path, $contents, Config $config)
    {
        // TODO: Implement update() method.
    }

    /**
     * @inheritDoc
     */
    public function updateStream($path, $resource, Config $config)
    {
        // TODO: Implement updateStream() method.
    }

    /**
     * @inheritDoc
     */
    public function rename($path, $newpath)
    {
        // TODO: Implement rename() method.
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
    public function setVisibility($path, $visibility)
    {
        // TODO: Implement setVisibility() method.
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
        // TODO: Implement read() method.
    }

    /**
     * @inheritDoc
     */
    public function readStream($path)
    {
        // TODO: Implement readStream() method.
    }

    /**
     * @inheritDoc
     */
    public function listContents($directory = '', $recursive = false)
    {
        // TODO: Implement listContents() method.
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

    /**
     * @inheritDoc
     */
    public function getVisibility($path)
    {
        // TODO: Implement getVisibility() method.
    }
}
