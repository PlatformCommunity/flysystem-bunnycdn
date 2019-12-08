<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;


use BunnyCDNStorageException;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Adapter\Polyfill\StreamedCopyTrait;
use League\Flysystem\Config;
use League\Flysystem\Exception;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\NotSupportedException;
use League\Flysystem\UnreadableFileException;
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
     * @param string $path
     * @param resource $resource
     * @param Config $config
     * @return array|false|void
     */
    public function writeStream($path, $resource, Config $config)
    {
        throw new NotSupportedException('BunnyCDN does not support steam writing, use ->write() instead');
    }

    /**
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @return array|bool|false
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * @param string $path
     * @param resource $resource
     * @param Config $config
     * @return array|false|void
     */
    public function updateStream($path, $resource, Config $config)
    {
        throw new NotSupportedException('BunnyCDN does not support steam updating, use ->update() instead');
    }

    /**
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function rename($path, $newpath)
    {
        $this->write($newpath, $this->read($path), new Config());
        return $this->delete($path);
    }

    /**
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function copy($path, $newpath)
    {
        return $this->write($newpath, $this->read($path), new Config());

    }

    /**
     * @param string $path
     * @return bool
     */
    public function delete($path)
    {
        return !$this->bunnyCDNStorage->deleteObject($path);
    }

    /**
     * @param string $dirname
     * @return bool|void
     */
    public function deleteDir($dirname)
    {
        return !$this->bunnyCDNStorage->deleteObject($dirname);
    }

    /**
     * @param string $dirname
     * @param Config $config
     * @return array|false|void
     */
    public function createDir($dirname, Config $config)
    {
        $temp_pointer = tmpfile();
        fwrite($temp_pointer, '');

        /** @var string $url */
        $url = stream_get_meta_data($temp_pointer)['uri'];

        try {
            $this->bunnyCDNStorage->uploadFile(
                $url,
                $this->bunnyCDNStorage->storageZoneName . '/' . $dirname
            );
        } catch (BunnyCDNStorageException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param string $path
     * @return bool
     */
    public function has($path)
    {
        return count(array_filter($this->bunnyCDNStorage->getStorageObjects(
                $this->bunnyCDNStorage->storageZoneName . '/' . $path
            ), function ($value) use ($path) {
                return
                    $value->Path . $value->ObjectName === '/' . $this->normalizePath($this->bunnyCDNStorage->storageZoneName . '/' . $path);
            }, ARRAY_FILTER_USE_BOTH)) === 1;
    }

    /**
     * @param string $path
     * @return array|bool|false|string
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
     * @param string $path
     * @return array|false|void
     */
    public function readStream($path)
    {
        throw new NotSupportedException('BunnyCDN does not support steam reading, use ->read() instead');
    }

    /**
     * @param string $directory
     * @param bool $recursive
     * @return array|mixed
     */
    public function listContents($directory = '', $recursive = false)
    {
        return $this->bunnyCDNStorage->getStorageObjects(
            $this->bunnyCDNStorage->storageZoneName . '/' . $directory
        );
    }

    /**
     * @param $path
     * @return mixed
     * @throws UnreadableFileException
     * @throws FileNotFoundException
     */
    private function getIndividualFile($path)
    {
        $files = array_filter($this->bunnyCDNStorage->getStorageObjects(
            $this->bunnyCDNStorage->storageZoneName . '/' . $path
        ), function ($value) use ($path) {
            return $value->Path . $value->ObjectName === '/' . $this->normalizePath($this->bunnyCDNStorage->storageZoneName . '/' . $path);
        }, ARRAY_FILTER_USE_BOTH);

        // Check that the path isn't returning more than one file / folder
        if (count($files) > 1) {
            throw new UnreadableFileException('More than one file was returned for path:"' . $path . '", contact package author.');
        }

        // Check 404
        if (count($files) === 0) {
            throw new FileNotFoundException('Could not find file: "' . $path . '".');
        }

        return array_values($files)[0];
    }

    /**
     * @param string $path
     * @return array|false
     */
    public function getMetadata($path)
    {
        try {
            return get_object_vars($this->getIndividualFile($path));
        } catch (FileNotFoundException $e) {
            return false;
        } catch (UnreadableFileException $e) {
            return false;
        }
    }

    /**
     * @param string $path
     * @return integer
     */
    public function getSize($path)
    {
        try {
            return $this->getIndividualFile($path)->Length;
        } catch (FileNotFoundException $e) {
            return false;
        } catch (UnreadableFileException $e) {
            return false;
        }
    }

    /**
     * @param string $path
     * @return array|false|void
     */
    public function getMimetype($path)
    {
        throw new NotSupportedException('BunnyCDN does not provide Mimetype information');
    }

    /**
     * @param string $path
     * @return array|false|void
     */
    public function getTimestamp($path)
    {
        try {
            return strtotime($this->getIndividualFile($path)->LastChanged);
        } catch (FileNotFoundException $e) {
            return false;
        } catch (UnreadableFileException $e) {
            return false;
        }
    }

    /**
     * @param $path
     * @param null $isDirectory
     * @return false|string|string[]
     * @throws Exception
     */
    private function normalizePath($path, $isDirectory = NULL)
    {
        $path = str_replace('\\', '/', $path);
        if ($isDirectory !== NULL) {
            if ($isDirectory) {
                if (!$this->endsWith($path, '/')) {
                    $path = $path . "/";
                }
            } else if ($this->endsWith($path, '/') && $path !== '/') {
                throw new Exception('The requested path is invalid.');
            }
        }

        // Remove double slashes
        while (strpos($path, '//') !== false) {
            $path = str_replace('//', '/', $path);
        }

        // Remove the starting slash
        if (strpos($path, '/') === 0) {
            $path = substr($path, 1);
        }

        return $path;
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    private function startsWith($haystack, $needle)
    {
        return (strpos($haystack, $needle) === 0);
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    private function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }
}
