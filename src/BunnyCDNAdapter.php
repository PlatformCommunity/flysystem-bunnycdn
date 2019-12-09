<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

use BunnyCDN\Storage\BunnyCDNStorage;
use BunnyCDN\Storage\Exceptions\BunnyCDNStorageException;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Adapter\Polyfill\StreamedCopyTrait;
use League\Flysystem\Config;
use League\Flysystem\Exception;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\NotSupportedException;
use League\Flysystem\UnreadableFileException;

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
        // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        return true;
    }

    /**
     * @codeCoverageIgnore
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
     * @codeCoverageIgnore
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
     * @throws Exception
     * @throws BunnyCDNStorageException
     */
    public function rename($path, $newpath)
    {
        $this->write($newpath, $this->read($path)['contents'], new Config());
        return $this->delete($path);
    }

    /**
     * @param string $path
     * @param string $newpath
     * @return bool
     * @throws Exception
     * @throws BunnyCDNStorageException
     */
    public function copy($path, $newpath)
    {
        return $this->write($newpath, $this->read($path)['contents'], new Config());
    }

    /**
     * @param string $path
     * @return bool
     */
    public function delete($path)
    {
        try {
            return !$this->bunnyCDNStorage->deleteObject($path);
        // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param string $dirname
     * @return bool|void
     */
    public function deleteDir($dirname)
    {
        try {
            return !$this->bunnyCDNStorage->deleteObject($dirname);
        // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd
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
        // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        return true;
    }

    /**
     * @param string $path
     * @return bool
     */
    public function has($path)
    {
        try {
            $file = self::splitPathIntoDirectoryAndFile($path)['file'];
            $directory = self::splitPathIntoDirectoryAndFile($path)['dir'];

            return count(array_filter($this->bunnyCDNStorage->getStorageObjects(
                    $this->bunnyCDNStorage->storageZoneName . '/' . $directory
                ), function ($value) use ($file, $directory) {
                    return $value->Path . $value->ObjectName === '/' . self::normalizePath(
                            $this->bunnyCDNStorage->storageZoneName . '/' . $directory . ((bool) $file ? '/' : '') . $file,
                            $file === null
                        );
                }, ARRAY_FILTER_USE_BOTH)) === 1;
        // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param string $path
     * @return array|bool
     * @throws Exception
     * @throws BunnyCDNStorageException
     */
    public function read($path)
    {
        $temp_pointer = tmpfile();

        try {
            $this->bunnyCDNStorage->downloadFile(
                self::normalizePath($this->bunnyCDNStorage->storageZoneName . '/' . $path),
                stream_get_meta_data($temp_pointer)['uri']
            );
        // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        $data = $this->getMetadata($path);
        $data['contents'] = (string) file_get_contents(stream_get_meta_data($temp_pointer)['uri']);

        return $data;
    }

    /**
     * @codeCoverageIgnore
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
     * @throws BunnyCDNStorageException
     */
    public function listContents($directory = '', $recursive = false)
    {
        return $this->bunnyCDNStorage->getStorageObjects(
            $this->bunnyCDNStorage->storageZoneName . '/' . $directory
        );
    }

    /**
     * Splits a path into a file and a directory
     * @param $path
     * @return array
     */
    protected static function splitPathIntoDirectoryAndFile($path) {
        $path = self::endsWith($path, '/') ? substr($path, 0, -1) : $path;
        $sub = explode('/', $path);
        $file = array_pop($sub);
        $directory = implode('/', $sub);

        return [
            'file' => $file,
            'dir' => $directory
        ];
    }

    /**
     * @param $path
     * @return mixed
     * @throws UnreadableFileException
     * @throws FileNotFoundException
     * @throws BunnyCDNStorageException
     */
    protected function getObject($path)
    {
        $file = self::splitPathIntoDirectoryAndFile($path)['file'];
        $directory = self::splitPathIntoDirectoryAndFile($path)['dir'];

        $files = array_filter($this->bunnyCDNStorage->getStorageObjects(
            $this->bunnyCDNStorage->storageZoneName . '/' . $directory
        ), function ($value) use ($file, $directory) {
            return $value->Path . $value->ObjectName === '/' . self::normalizePath(
                $this->bunnyCDNStorage->storageZoneName . '/' . $directory . ((bool) $file ? '/' : '') . $file,
                $file === null
                );
        }, ARRAY_FILTER_USE_BOTH);

        // Check that the path isn't returning more than one file / folder
        if (count($files) > 1) {
            // @codeCoverageIgnoreStart
            throw new UnreadableFileException('More than one file was returned for path:"' . $path . '", contact package author.');
            // @codeCoverageIgnoreEnd
        }

        // Check 404
        if (count($files) === 0) {
            // @codeCoverageIgnoreStart
            throw new FileNotFoundException('Could not find file: "' . $path . '".');
            // @codeCoverageIgnoreEnd
        }

        return array_values($files)[0];
    }

    /**
     * {@inheritdoc}
     */
    protected function normalizeObject(\stdClass $fileObject)
    {
        return [
            'type'      => $fileObject->IsDirectory ? 'dir' : 'file',
            'dirname'   => self::splitPathIntoDirectoryAndFile($fileObject->Path)['dir'],
            'mimetype'  => '',
            'guid' => $fileObject->Guid,
            'path'      => $fileObject->Path,
            'object_name' => $fileObject->ObjectName,
            'size'      => $fileObject->Length,
            'timestamp' => strtotime($fileObject->LastChanged),
            'server_id' => $fileObject->ServerId,
            'user_id' => $fileObject->UserId,
            'last_changed' => $fileObject->LastChanged,
            'date_created' => $fileObject->DateCreated,
            'storage_zone_name' => $fileObject->StorageZoneName,
            'storage_zone_id' => $fileObject->StorageZoneId,
            'checksum' => $fileObject->Checksum,
            'replicated_zones' => $fileObject->ReplicatedZones,
        ];
    }

    /**
     * @param string $path
     * @return array|false
     * @throws BunnyCDNStorageException
     * @throws FileNotFoundException
     * @throws UnreadableFileException
     */
    public function getMetadata($path)
    {

        return $this->normalizeObject($this->getObject($path));
    }

    /**
     * @param string $path
     * @return integer
     * @throws BunnyCDNStorageException
     * @throws FileNotFoundException
     * @throws UnreadableFileException
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @codeCoverageIgnore
     * @param string $path
     * @return array
     * @throws BunnyCDNStorageException
     * @throws FileNotFoundException
     * @throws UnreadableFileException
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @param string $path
     * @return array
     * @throws BunnyCDNStorageException
     * @throws FileNotFoundException
     * @throws UnreadableFileException
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @param $path
     * @param null $isDirectory
     * @return false|string|string[]
     * @throws Exception
     */
    protected static function normalizePath($path, $isDirectory = NULL)
    {
        $path = str_replace('\\', '/', $path);
        if ($isDirectory !== NULL) {
            if ($isDirectory) {
                if (!self::endsWith($path, '/')) {
                    $path = $path . "/";
                }
            // @codeCoverageIgnoreStart
            } else if (self::endsWith($path, '/') && $path !== '/') {
                throw new Exception('The requested path is invalid.');
            }
            // @codeCoverageIgnoreEnd
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
     * @codeCoverageIgnore
     * @param $haystack
     * @param $needle
     * @return bool
     */
    protected static function startsWith($haystack, $needle)
    {
        return strpos($haystack, $needle) === 0;
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    protected static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }

        return substr($haystack, -$length) === $needle;
    }
}
