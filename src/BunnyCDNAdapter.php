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
use League\Flysystem\Visibility;
use RuntimeException;
use stdClass;
use function PHPUnit\Framework\stringContains;

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
     * @param BunnyCDNClient $client
     * @param string $pullzone_url
     */
    public function __construct(BunnyCDNClient $client, string $pullzone_url = '')
    {
        $this->client = $client;
        $this->pullzone_url = $pullzone_url;
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
        try {
            $this->client->upload($path, $contents);
        } catch (Exceptions\BunnyCDNException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage());
        }

    }

    /**
     * @param $path
     * @return string
     */
    public function read($path): string
    {
        try {
            return $this->client->download($path);
        } catch (Exceptions\BunnyCDNException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage());
        }
    }

    /**
     * @param string $path
     * @param bool $deep
     * @return iterable
     */
    public function listContents(string $path = '', bool $deep = false): iterable
    {
        try {
            $entries = $this->client->list($path);
        } catch (Exceptions\BunnyCDNException $e) {
            throw UnableToRetrieveMetadata::create($path, 'folder', $e->getMessage());
        }

        foreach ($entries as $item) {
            yield $this->normalizeObject($item);
        }

//        return new DirectoryListing($contents, $deep);
//        return array_map(function($item) {
//            return $this->normalizeObject($item);
//        }, $entries);

//        return $entries;
    }

    /**
     * @param array $bunny_file_array
     * @return StorageAttributes
     */
    protected function normalizeObject(array $bunny_file_array): StorageAttributes
    {
        return match ($bunny_file_array['IsDirectory']) {
            true => new DirectoryAttributes(
                Util::normalizePath(
                    str_replace(
                        $bunny_file_array['StorageZoneName'] . '/',
                        '/',
                        $bunny_file_array['Path'] . $bunny_file_array['ObjectName']
                    )
                )
            ),
            false => new FileAttributes(
                Util::normalizePath(
                    str_replace(
                        $bunny_file_array['StorageZoneName'] . '/',
                        '/',
                        $bunny_file_array['Path'] . $bunny_file_array['ObjectName']
                    )
                ),
                $bunny_file_array['Length'],
                Visibility::PUBLIC,
                date_create_from_format('Y-m-d\TH:i:s.v', $bunny_file_array['LastChanged'])->getTimestamp(),
                $bunny_file_array['ContentType'],
                $this->extractExtraMetadata($bunny_file_array)
            )
        };
    }

    /**
     * @param array $bunny_file_array
     * @return array
     */
    private function extractExtraMetadata(array $bunny_file_array): array
    {
        return [
            'type'      => $bunny_file_array['IsDirectory'] ? 'dir' : 'file',
            'dirname'   => Util::splitPathIntoDirectoryAndFile($bunny_file_array['Path'])['dir'],
            'guid' => $bunny_file_array['Guid'],
            'object_name' => $bunny_file_array['ObjectName'],
            'timestamp' => date_create_from_format('Y-m-d\TH:i:s.v', $bunny_file_array['LastChanged'])->getTimestamp(),
            'server_id' => $bunny_file_array['ServerId'],
            'user_id' => $bunny_file_array['UserId'],
            'date_created' => $bunny_file_array['DateCreated'],
            'storage_zone_name' => $bunny_file_array['StorageZoneName'],
            'storage_zone_id' => $bunny_file_array['StorageZoneId'],
            'checksum' => $bunny_file_array['Checksum'],
            'replicated_zones' => $bunny_file_array['ReplicatedZones'],
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
        /** @var resource $readStream */
        $readStream = fopen('data:text/plain;base64,' . base64_encode($this->read($path)),'r');

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
            $this->client->delete(
                rtrim($path, '/') . '/'
            );
        } catch (Exceptions\BunnyCDNException $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage());
        }

    }

    /**
     * @throws UnableToCreateDirectory
     * @throws FilesystemException
     */
    public function createDirectory(string $path, Config $config): void
    {
        try {
            $this->client->make_directory($path);
        } catch (Exceptions\BunnyCDNException $e) {
            # Lol apparently this is "idempotent" but there's an exception... Sure whatever..
            match ($e->getMessage()) {
                'Directory already exists' => "",
                default => throw UnableToCreateDirectory::atLocation($path, $e->getMessage())
            };
        }

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
     */
    public function visibility(string $path): FileAttributes
    {
        try {
            return new FileAttributes($this->getObject($path)->path(), null, $this->pullzone_url ? 'public' : 'private');
        } catch (UnableToReadFile $e) {
            throw new UnableToRetrieveMetadata($e->getMessage());
        }
    }

    /**
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            $object = $this->getObject($path);

            if($object instanceof DirectoryAttributes) {
                throw new UnableToRetrieveMetadata('Cannot retrieve mimetype of folder');
            }

            /** @var FileAttributes $object */
            if (!$object->mimeType()) {
                throw new UnableToRetrieveMetadata('Unknown Mimetype');
            }

            return $object;
        } catch (UnableToReadFile $e) {
            throw new UnableToRetrieveMetadata($e->getMessage());
        }
    }

    /**
     * @param $path
     * @return mixed
     */
    protected function getObject($path): StorageAttributes
    {
        $list = (new DirectoryListing($this->listContents()))
            ->filter(function (StorageAttributes $item) use ($path) {
                return $item->path() === $path;
            })->toArray();

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
        try {
            return $this->getObject($path);
        } catch (UnableToReadFile $e) {
            throw new UnableToRetrieveMetadata($e->getMessage());
        }
    }

    /**
     * @param string $path
     * @return FileAttributes
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            $object = $this->getObject($path);

            if($object instanceof DirectoryAttributes) {
                throw new UnableToRetrieveMetadata('Cannot retrieve size of folder');
            }

            return $object;
        } catch (UnableToReadFile $e) {
            throw new UnableToRetrieveMetadata($e->getMessage());
        }
    }

    /**
     * @throws UnableToMoveFile
     * @throws FilesystemException
     */
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->write($destination, $this->read($source), new Config());
            $this->delete($source);
        } catch (UnableToReadFile $e) {
            throw new UnableToMoveFile($e->getMessage());
        }
    }

    /**
     * @param $path
     * @return void
     */
    public function delete($path): void
    {
        try {
            $this->client->delete($path);
        } catch (Exceptions\BunnyCDNException $e) {
            if(!str_contains($e->getMessage(), '404')) { # Urgh
                throw UnableToDeleteFile::atLocation($path, $e->getMessage());
            }
        }

    }

    /**
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
        $list = new DirectoryListing($this->listContents(
            Util::splitPathIntoDirectoryAndFile($path)['dir']
        ));

        $count = $list->filter(function(StorageAttributes $item) use ($path) {
            return Util::normalizePath($item->path()) === Util::normalizePath($path);
        })->toArray();

        return (bool)count($count);
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
