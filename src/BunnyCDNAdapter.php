<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

use BunnyCDN\Storage\BunnyCDNStorage;
use BunnyCDN\Storage\Exceptions\BunnyCDNStorageException;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\InvalidVisibilityProvided;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use RuntimeException;
use stdClass;
use League\Flysystem\PathPrefixer;

class BunnyCDNAdapter implements FilesystemAdapter
{
//    use NotSupportingVisibilityTrait;
//    use StreamedCopyTrait;
//    use StreamedTrait;

    /**
     * The BunnyCDN Storage Container
     * @var BunnyCDNStorage
     */
    protected $bunnyCDNStorage;

    /**
     * @var PathPrefixer
     */
    private $prefixer;

    /**
     * Pull Zone URL
     * @var string
     */
    private $pullzone_url;

    /**
     * @param BunnyCDNStorage $bunnyCDNStorage
     * @param string $pullzone_url
     */
    public function __construct(BunnyCDNStorage $bunnyCDNStorage, string $pullzone_url = '')
    {
        $this->bunnyCDNStorage = $bunnyCDNStorage;
        $this->prefixer = new PathPrefixer($this->bunnyCDNStorage->storageZoneName);
        $this->pullzone_url = $pullzone_url;
    }

    /**
     * @param $path
     * @param $contents
     * @param Config $config
     */
    public function write($path, $contents, Config $config): void
    {
        $temp_pointer = tmpfile();
        fwrite($temp_pointer, $contents);

        /** @var string $url */
        $url = stream_get_meta_data($temp_pointer)['uri'];

        try {
            $this->bunnyCDNStorage->uploadFile(
                $url,
                $this->fullPath($path)
            );
        // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage());
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param $source
     * @param $destination
     * @param Config $config
     * @return void
     */
    public function copy($source, $destination, Config $config): void
    {
        try {
            $this->write($destination, $this->read($source), new Config());
        } catch(UnableToReadFile|UnableToWriteFile $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    /**
     * @param $path
     * @return void
     */
    public function delete($path): void
    {
        try {
            $this->bunnyCDNStorage->deleteObject(
                $this->fullPath($path)
            );
        // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage());
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param $path
     * @return string
     */
    public function read($path): string
    {
        $temp_pointer = tmpfile();

        try {
            $this->bunnyCDNStorage->downloadFile(
                $this->fullPath($path),
                stream_get_meta_data($temp_pointer)['uri']
            );
        // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage());
        }
        // @codeCoverageIgnoreEnd
        return file_get_contents(stream_get_meta_data($temp_pointer)['uri']);
    }

    /**
     * @param $path
     * @param $deep
     * @return iterable
     * @throws BunnyCDNStorageException
     */
    public function listContents($path = '', $deep = false): iterable
    {
        $entries = array_map(function($file) {
            return $this->normalizeObject($file);
        }, $this->bunnyCDNStorage->getStorageObjects(
            $this->fullPath($path)
        ));

        return array_filter($entries);
    }

    /**
     * @param $path
     * @return mixed
     */
    protected function getObject($path)
    {
        $file = Util::splitPathIntoDirectoryAndFile($path)['file'];
        $directory = Util::splitPathIntoDirectoryAndFile($path)['dir'];

        try {
            $files = array_filter($this->bunnyCDNStorage->getStorageObjects(
                $this->fullPath($directory)
            ), function ($value) use ($file, $directory) {
                return $value->Path . $value->ObjectName === $this->fullPath($directory . '/' . $file);
            }, ARRAY_FILTER_USE_BOTH);
        } catch (BunnyCDNStorageException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage());
        }


        // Check that the path isn't returning more than one file / folder
        if (count($files) > 1) {
            // @codeCoverageIgnoreStart
            throw UnableToReadFile::fromLocation($path, 'More than one file was returned for path:"' . $path . '", contact package author.');
            // @codeCoverageIgnoreEnd
        }

        // Check 404
        if (count($files) === 0) {
            // @codeCoverageIgnoreStart
            throw UnableToReadFile::fromLocation($path, 'Could not find file: "' . $path . '".');
            // @codeCoverageIgnoreEnd
        }

        return array_values($files)[0];
    }

    /**
     * @param stdClass $fileObject
     * @return array
     */
    protected function normalizeObject(stdClass $fileObject): array
    {
        return [
            'type'      => $fileObject->IsDirectory ? 'dir' : 'file',
            'dirname'   => trim($this->prefixer->stripPrefix($fileObject->Path), "/"),
            'mimetype'  => '',
            'guid' => $fileObject->Guid,
            'path'      => Util::normalizePath($this->prefixer->stripPrefix($fileObject->Path) . $fileObject->ObjectName),
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
     * @param $path
     * @return string
     */
    private function fullPath($path): string
    {
        return '/' . $this->prefixer->prefixPath('/' . Util::normalizePath($path));
    }

    /**
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        try {
            $file = Util::splitPathIntoDirectoryAndFile($path)['file'];
            $directory = Util::splitPathIntoDirectoryAndFile($path)['dir'];

            return count(array_filter($this->bunnyCDNStorage->getStorageObjects(
                    $this->fullPath($directory)
                ), function ($value) use ($file, $directory) {
                    return $value->Path . $value->ObjectName === $this->fullPath($directory . '/' . $file);
                }, ARRAY_FILTER_USE_BOTH)) === 1;
            // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param string $path
     * @param $contents
     * @param Config $config
     * @return void
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, $contents, $config);
    }

    /**
     * @param string $path
     * @return resource
     */
    public function readStream(string $path)
    {
        $location = $this->prefixer->prefixPath($path);

        /** @var resource $readStream */
        $readStream = fopen('php://temp', 'w+');

        rewind($readStream);

        return $readStream;
    }

    /**
     * @throws UnableToDeleteDirectory
     * @throws FilesystemException
     */
    public function deleteDirectory(string $path): void
    {
        try {
            $this->bunnyCDNStorage->deleteObject(
                rtrim($this->fullPath($path), '/').'/'
            );
            // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage());
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @throws UnableToCreateDirectory
     * @throws FilesystemException
     */
    public function createDirectory(string $path, Config $config): void
    {
        $temp_pointer = tmpfile();
        fwrite($temp_pointer, '', 0);

        /** @var string $url */
        $url = stream_get_meta_data($temp_pointer)['uri'];

        try {
            $this->bunnyCDNStorage->uploadFile(
                $url,
                rtrim($this->fullPath($path), '/').'/'
            );
            // @codeCoverageIgnoreStart
        } catch (BunnyCDNStorageException $e) {
            throw UnableToCreateDirectory::atLocation($path, $e->getMessage());
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @throws InvalidVisibilityProvided
     * @throws FilesystemException
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, "BunnyCDN does not support visibility");
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path, "BunnyCDN does not support visibility");
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function mimeType(string $path): FileAttributes
    {
        $mimeType = $this->normalizeObject($this->getObject($path))['mimetype'];

        return new FileAttributes($path, $mimeType);
    }

    /**
     * @param string $path
     * @return FileAttributes
     */
    public function lastModified(string $path): FileAttributes
    {
        $lastModified = (int)strtotime($this->normalizeObject($this->getObject($path))['last_changed']);

        return new FileAttributes($path, null, null, $lastModified);
    }

    /**
     * @param string $path
     * @return FileAttributes
     */
    public function fileSize(string $path): FileAttributes
    {
        $fileSize = $this->normalizeObject($this->getObject($path))['size'];

        return new FileAttributes($path, $fileSize);
    }

    /**
     * @throws UnableToMoveFile
     * @throws FilesystemException
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $this->write($destination, $this->read($source), new Config());
        $this->delete($source);
    }

    /**
     * getURL method for Laravel users who want to use BunnyCDN's PullZone to retrieve a public URL
     * @param string $path
     * @return string
     */
    public function getUrl(string $path): string
    {
        if($this->pullzone_url === '') {
            throw new RuntimeException('In order to get a visible URL for a BunnyCDN object, you must pass the "pullzone_url" parameter to the BunnyCDNAdapter.');
        }

        return rtrim($this->pullzone_url, '/').'/'.ltrim($path, '/');
    }
}
