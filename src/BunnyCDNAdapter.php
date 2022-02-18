<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\InvalidVisibilityProvided;
use League\Flysystem\PathPrefixer;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use RuntimeException;
use stdClass;

class BunnyCDNAdapter implements FilesystemAdapter
{

    /**
     * Pull Zone URL
     * @var string
     */
    private string $pullzone_url;

    /**
     * @var BunnyCDNClient
     */
    private BunnyCDNClient $client;

    /**
     * @param string $pull_zone
     * @param string $api_key
     * @param string $pullzone_url
     */
    public function __construct(string $pull_zone, string $api_key, string $pullzone_url = '')
    {
        $this->client = new BunnyCDNClient($pullzone_url, $api_key);
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
        } catch (UnableToReadFile|UnableToWriteFile $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
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
     * @param $path
     * @return string
     */
    private function fullPath($path): string
    {
        return '/' . $this->prefixer->prefixPath('/' . Util::normalizePath($path));
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
     * @param string $path
     * @param bool $deep
     * @throws BunnyCDNStorageException
     */
    public function listContents(string $path = '', bool $deep = false): iterable
    {
        $entries = $this->bunnyCDNStorage->getStorageObjects(
            $this->fullPath($path)
        );

        foreach ($entries as $item) {
            yield $this->normalizeObject($item);
        }

//        return new DirectoryListing($contents, $deep);
    }

    /**
     * @param stdClass $fileObject
     * @return StorageAttributes
     */
    protected function normalizeObject(stdClass $fileObject): StorageAttributes
    {
        if($fileObject->IsDirectory) {
            return new DirectoryAttributes(Util::normalizePath($this->prefixer->stripPrefix($fileObject->Path) . $fileObject->ObjectName));
        }

        return new FileAttributes(
            Util::normalizePath($this->prefixer->stripPrefix($fileObject->Path) . $fileObject->ObjectName),
            $fileObject->Length,
            null,
            (int)$fileObject->LastChanged,
            '',
            $this->extractExtraMetadata($fileObject)
        );
    }

    /**
     * @param stdClass $fileObject
     * @return array
     */
    private function extractExtraMetadata(stdClass $fileObject): array
    {
        return [
            'type'      => $fileObject->IsDirectory ? 'dir' : 'file',
            'dirname'   => Util::splitPathIntoDirectoryAndFile($fileObject->Path)['dir'],
            'guid' => $fileObject->Guid,
            'object_name' => $fileObject->ObjectName,
            'timestamp' => strtotime($fileObject->LastChanged),
            'server_id' => $fileObject->ServerId,
            'user_id' => $fileObject->UserId,
            'date_created' => $fileObject->DateCreated,
            'storage_zone_name' => $fileObject->StorageZoneName,
            'storage_zone_id' => $fileObject->StorageZoneId,
            'checksum' => $fileObject->Checksum,
            'replicated_zones' => $fileObject->ReplicatedZones,
        ];
    }

    /**
     * @param $path
     * @param $contents
     * @param Config $config
     * @return void
     */
    public function writeStream($path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    /**
     * @param $path
     * @return resource
     */
    public function readStream($path)
    {
        $location = $this->prefixer->prefixPath($path);

        /** @var resource $readStream */
        $readStream = fopen('data://text/plain,' . $this->read($path),'r');

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
                rtrim($this->fullPath($path), '/') . '/'
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
                rtrim($this->fullPath($path), '/') . '/'
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
        // throw UnableToRetrieveMetadata::visibility($path, "BunnyCDN does not support visibility");
        return new FileAttributes($path, null, 'public');
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->getObject($path);
    }

    /**
     * @param $path
     * @return mixed
     */
    protected function getObject($path)
    {
        try {
            $list = (new DirectoryListing($this->listContents()))
                ->filter(function (StorageAttributes $item) use ($path) {
                    return $item->path() === $path;
                })->toArray();
        } catch (BunnyCDNStorageException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage());
        }

        if (count($list) === 1) {
            return $list[0];
        } elseif (count($list) > 1) {
            throw UnableToReadFile::fromLocation($path, 'More than one file was returned for path:"' . $path . '", contact package author.');
        } else {
            throw UnableToReadFile::fromLocation($path, 'Error 404:"' . $path . '"');
        }
    }

    /**
     * @param string $path
     * @return FileAttributes
     */
    public function lastModified(string $path): FileAttributes
    {
        return $this->getObject($path);
    }

    /**
     * @param string $path
     * @return FileAttributes
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->getObject($path);
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
     * @throws FilesystemException
     * @throws UnableToCheckExistence
     */
    public function directoryExists(string $path): bool
    {
        return $this->fileExists($path);
    }

    /**
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        try {
            $list = new DirectoryListing($this->listContents());

            $count = $list->filter(function(StorageAttributes $item) use ($path) {
                return $item->path() === $path;
            })->toArray();

            return (bool)count($count);
        } catch (BunnyCDNStorageException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * getURL method for Laravel users who want to use BunnyCDN's PullZone to retrieve a public URL
     * @param string $path
     * @return string
     */
    public function getUrl(string $path): string
    {
        if ($this->pullzone_url === '') {
            throw new RuntimeException('In order to get a visible URL for a BunnyCDN object, you must pass the "pullzone_url" parameter to the BunnyCDNAdapter.');
        }

        return rtrim($this->pullzone_url, '/') . '/' . ltrim($path, '/');
    }
}
