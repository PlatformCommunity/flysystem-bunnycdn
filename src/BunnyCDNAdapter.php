<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use League\Flysystem\CalculateChecksumFromStream;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\InvalidVisibilityProvided;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\NotFoundException;
use RuntimeException;
use TypeError;

class BunnyCDNAdapter implements FilesystemAdapter, PublicUrlGenerator, ChecksumProvider
{
    use CalculateChecksumFromStream;

    public function __construct(private BunnyCDNClient $client, private string $pullzone_url = '')
    {
        if (\func_num_args() > 2 && (string) \func_get_arg(2) !== '') {
            throw new \RuntimeException('PrefixPath is no longer supported directly. Use PathPrefixedAdapter instead: https://flysystem.thephpleague.com/docs/adapter/path-prefixing/');
        }
    }

    /**
     * @param $source
     * @param $destination
     * @param  Config  $config
     * @return void
     */
    public function copy($source, $destination, Config $config): void
    {
        try {
            $sourceLength = \strlen($source);

            foreach ($this->getFiles($source) as $file) {
                $this->copyFile($file, $destination.\substr($file, $sourceLength), $config);
            }
        } catch (UnableToReadFile|UnableToWriteFile $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    /**
     * @param $path
     * @param $contents
     * @param  Config  $config
     */
    public function write($path, $contents, Config $config): void
    {
        try {
            $this->client->upload($path, $contents);
            // @codeCoverageIgnoreStart
        } catch (Exceptions\BunnyCDNException $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage());
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param $path
     * @return string
     */
    public function read($path): string
    {
        try {
            return $this->client->download($path);
            // @codeCoverageIgnoreStart
        } catch (Exceptions\BunnyCDNException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage());
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param  string  $path
     * @param  bool  $deep
     * @return iterable
     */
    public function listContents(string $path, bool $deep): iterable
    {
        try {
            $entries = $this->client->list($path);
            // @codeCoverageIgnoreStart
        } catch (Exceptions\BunnyCDNException $e) {
            throw UnableToRetrieveMetadata::create($path, 'folder', $e->getMessage());
        }
        // @codeCoverageIgnoreEnd

        foreach ($entries as $item) {
            $content = $this->normalizeObject($item);
            yield $content;

            if ($deep && $content instanceof DirectoryAttributes) {
                foreach ($this->listContents($content->path(), $deep) as $deepItem) {
                    yield $deepItem;
                }
            }
        }
    }

    /**
     * @param  array  $bunny_file_array
     * @return StorageAttributes
     */
    protected function normalizeObject(array $bunny_file_array): StorageAttributes
    {
        $normalised_path = Util::normalizePath(
            Util::replaceFirst(
                $bunny_file_array['StorageZoneName'].'/',
                '/',
                $bunny_file_array['Path'].$bunny_file_array['ObjectName']
            )
        );

        return match ($bunny_file_array['IsDirectory']) {
            true => new DirectoryAttributes(
                $normalised_path
            ),
            false => new FileAttributes(
                $normalised_path,
                $bunny_file_array['Length'],
                Visibility::PUBLIC,
                self::parse_bunny_timestamp($bunny_file_array['LastChanged']),
                $bunny_file_array['ContentType'] ?: $this->detectMimeType($bunny_file_array['Path'].$bunny_file_array['ObjectName']),
                $this->extractExtraMetadata($bunny_file_array)
            )
        };
    }

    /**
     * @param  array  $bunny_file_array
     * @return array
     */
    private function extractExtraMetadata(array $bunny_file_array): array
    {
        return [
            'type' => $bunny_file_array['IsDirectory'] ? 'dir' : 'file',
            'dirname' => Util::splitPathIntoDirectoryAndFile($bunny_file_array['Path'])['dir'],
            'guid' => $bunny_file_array['Guid'],
            'object_name' => $bunny_file_array['ObjectName'],
            'timestamp' => self::parse_bunny_timestamp($bunny_file_array['LastChanged']),
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
     * Detects the mime type from the provided file path
     *
     * @param  string  $path
     * @return string
     */
    public function detectMimeType(string $path): string
    {
        try {
            $detector = new FinfoMimeTypeDetector();
            $mimeType = $detector->detectMimeTypeFromPath($path);

            if (! $mimeType) {
                return $detector->detectMimeTypeFromBuffer(stream_get_contents($this->readStream($path), 80));
            }

            return $mimeType;
        } catch (Exception) {
            return '';
        }
    }

    /**
     * @param $path
     * @param $contents
     * @param  Config  $config
     * @return void
     */
    public function writeStream($path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    /**
     * @param  WriteBatchFile[]  $writeBatches
     * @param  Config  $config
     * @return void
     */
    public function writeBatch(array $writeBatches, Config $config): void
    {
        $concurrency = (int) $config->get('concurrency', 50);

        foreach (\array_chunk($writeBatches, $concurrency) as $batch) {
            $requests = function () use ($batch) {
                /** @var WriteBatchFile $file */
                foreach ($batch as $file) {
                    yield $this->client->getUploadRequest($file->targetPath, \file_get_contents($file->localPath));
                }
            };

            $pool = new Pool($this->client->guzzleClient, $requests(), [
                'concurrency' => $concurrency,
                'rejected' => function (RequestException|RuntimeException $reason, int $index) {
                    throw UnableToWriteFile::atLocation($index, $reason->getMessage());
                },
            ]);

            $pool->promise()->wait();
        }
    }

    /**
     * @param $path
     * @return resource
     *
     * @throws UnableToReadFile
     */
    public function readStream($path)
    {
        try {
            return $this->client->stream($path);
            // @codeCoverageIgnoreStart
        } catch (Exceptions\BunnyCDNException|Exceptions\NotFoundException $e) {
            throw UnableToReadFile::fromLocation($path, $e->getMessage());
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @throws UnableToDeleteDirectory
     * @throws FilesystemException
     */
    public function deleteDirectory(string $path): void
    {
        try {
            $this->client->delete(
                rtrim($path, '/').'/'
            );
            // @codeCoverageIgnoreStart
        } catch (NotFoundException) {
            // nth
        } catch (Exceptions\BunnyCDNException $e) {
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
        try {
            $this->client->make_directory($path);
            // @codeCoverageIgnoreStart
        } catch (Exceptions\BunnyCDNException $e) {
            // Lol apparently this is "idempotent" but there's an exception... Sure whatever..
            match ($e->getMessage()) {
                'Directory already exists' => '',
                default => throw UnableToCreateDirectory::atLocation($path, $e->getMessage())
            };
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @throws InvalidVisibilityProvided
     * @throws FilesystemException
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'BunnyCDN does not support visibility');
    }

    /**
     * @throws UnableToRetrieveMetadata
     */
    public function visibility(string $path): FileAttributes
    {
        try {
            return new FileAttributes($this->getObject($path)->path(), null, $this->pullzone_url ? 'public' : 'private');
        } catch (UnableToReadFile|TypeError $e) {
            throw new UnableToRetrieveMetadata($e->getMessage());
        }
    }

    /**
     * @param  string  $path
     * @return FileAttributes
     *
     * @codeCoverageIgnore
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            $object = $this->getObject($path);

            if ($object instanceof DirectoryAttributes) {
                throw new TypeError();
            }

            /** @var FileAttributes $object */
            if (! $object->mimeType()) {
                $mimeType = $this->detectMimeType($path);

                if (! $mimeType || $mimeType === 'text/plain') { // Really not happy about this being required by Fly's Test case
                    throw new UnableToRetrieveMetadata('Unknown Mimetype');
                }

                return new FileAttributes(
                    $path,
                    null,
                    null,
                    null,
                    $mimeType
                );
            }

            return $object;
        } catch (UnableToReadFile $e) {
            throw new UnableToRetrieveMetadata($e->getMessage());
        } catch (TypeError) {
            throw new UnableToRetrieveMetadata('Cannot retrieve mimeType of folder');
        }
    }

    /**
     * @param  string  $path
     * @return mixed
     */
    protected function getObject(string $path = ''): StorageAttributes
    {
        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $list = (new DirectoryListing($this->listContents($directory, false)))
            ->filter(function (StorageAttributes $item) use ($path) {
                return Util::normalizePath($item->path()) === $path;
            })->toArray();

        if (count($list) === 1) {
            return $list[0];
        }

        if (count($list) > 1) {
            // @codeCoverageIgnoreStart
            throw UnableToReadFile::fromLocation($path, 'More than one file was returned for path:"'.$path.'", contact package author.');
            // @codeCoverageIgnoreEnd
        }

        throw UnableToReadFile::fromLocation($path, 'Error 404:"'.$path.'"');
    }

    /**
     * @param  string  $path
     * @return FileAttributes
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            return $this->getObject($path);
        } catch (UnableToReadFile $e) {
            throw new UnableToRetrieveMetadata($e->getMessage());
        } catch (TypeError) {
            throw new UnableToRetrieveMetadata('Last Modified only accepts files as parameters, not directories');
        }
    }

    /**
     * @param  string  $path
     * @return FileAttributes
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            return $this->getObject($path);
        } catch (UnableToReadFile $e) {
            throw new UnableToRetrieveMetadata($e->getMessage());
        } catch (TypeError) {
            throw new UnableToRetrieveMetadata('Cannot retrieve size of folder');
        }
    }

    /**
     * @throws UnableToMoveFile
     * @throws FilesystemException
     */
    public function move(string $source, string $destination, Config $config): void
    {
        if ($source === $destination) {
            return;
        }

        try {
            /** @var array<string> $files */
            $files = iterator_to_array($this->getFiles($source));

            $sourceLength = \strlen($source);

            foreach ($files as $file) {
                $this->moveFile($file, $destination.\substr($file, $sourceLength), $config);
            }
        } catch (UnableToReadFile $e) {
            throw new UnableToMoveFile($e->getMessage());
        }
    }

    private function getFiles(string $source): iterable
    {
        $contents = iterator_to_array($this->listContents($source, true));

        if (\count($contents) === 0) {
            yield $source;

            return;
        }

        /** @var StorageAttributes $entry */
        foreach ($contents as $entry) {
            if ($entry->isFile() === false) {
                continue;
            }

            yield $entry->path();
        }
    }

    private function moveFile(string $source, string $destination, Config $config): void
    {
        $this->copyFile($source, $destination, $config);
        $this->delete($source);
    }

    private function copyFile(string $source, string $destination, Config $config): void
    {
        $this->write($destination, $this->read($source), $config);
    }

    /**
     * @param $path
     * @return void
     */
    public function delete($path): void
    {
        // if path is empty or ends with /, it's a directory.
        if (empty($path) || str_ends_with($path, '/')) {
            throw UnableToDeleteFile::atLocation($path, 'Deletion of directories prevented.');
        }

        try {
            $this->client->delete($path);
            // @codeCoverageIgnoreStart
        } catch (NotFoundException) {
            // nth
        } catch (Exceptions\BunnyCDNException $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage());
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @throws UnableToCheckExistence
     */
    public function directoryExists(string $path): bool
    {
        return $this->exists(StorageAttributes::TYPE_DIRECTORY, $path);
    }

    /**
     * @param  string  $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        return $this->exists(StorageAttributes::TYPE_FILE, $path);
    }

    /**
     * @param  string  $path
     * @param  Config  $config
     * @return string
     */
    public function checksum(string $path, Config $config): string
    {
        // for compatibility reasons, the default checksum algorithm is md5
        $algo = $config->get('checksum_algo', 'md5');

        if ($algo !== 'sha256') {
            return $this->calculateChecksumFromStream($path, $config);
        }

        try {
            $file = $this->getObject($path);
        } catch (UnableToReadFile $exception) {
            throw new UnableToProvideChecksum($exception->reason(), $path, $exception);
        }

        $metaData = $file->extraMetadata();

        if (empty($metaData['checksum']) || ! is_string($metaData['checksum'])) {
            throw new UnableToProvideChecksum('Checksum not available.', $path);
        }

        return \strtolower($metaData['checksum']);
    }

    /**
     * @deprecated use publicUrl instead
     *
     * @param  string  $path
     * @return string
     * @codeCoverageIgnore
     * @noinspection PhpUnused
     */
    public function getUrl(string $path): string
    {
        return $this->publicUrl($path, new Config());
    }

    /**
     * @param  string  $path
     * @param  Config  $config
     * @return string
     */
    public function publicUrl(string $path, Config $config): string
    {
        if ($this->pullzone_url === '') {
            throw new RuntimeException('In order to get a visible URL for a BunnyCDN object, you must pass the "pullzone_url" parameter to the BunnyCDNAdapter.');
        }

        return rtrim($this->pullzone_url, '/').'/'.ltrim($path, '/');
    }

    private static function parse_bunny_timestamp(string $timestamp): int
    {
        return (date_create_from_format('Y-m-d\TH:i:s.u', $timestamp) ?: date_create_from_format('Y-m-d\TH:i:s', $timestamp))->getTimestamp();
    }

    private function exists(string $type, string $path): bool
    {
        $list = new DirectoryListing($this->listContents(
            Util::splitPathIntoDirectoryAndFile($path)['dir'],
            false
        ));

        $count = $list->filter(function (StorageAttributes $item) use ($path, $type) {
            return $item->type() === $type && Util::normalizePath($item->path()) === Util::normalizePath($path);
        })->toArray();

        return (bool) count($count);
    }
}
