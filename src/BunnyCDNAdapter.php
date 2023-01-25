<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

use Exception;
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
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use DateTimeInterface;
use RuntimeException;
use TypeError;

class BunnyCDNAdapter implements FilesystemAdapter, PublicUrlGenerator, ChecksumProvider
{
    use CalculateChecksumFromStream;

    /**
     * @var BunnyCDNClient
     */
    private BunnyCDNClient $client;

    private ?BunnyPullZone $pullzone;

    /**
     * @param BunnyCDNClient $client
     * @param BunnyPullZone|null $pullzone
     */
    public function __construct(BunnyCDNClient $client, ?BunnyPullZone $pullzone = null)
    {
        $this->client = $client;
        $this->pullzone = $pullzone;
    }

    /**
     * Changes the pullzone currently in use on the adapter. Primarily used for testing.
     *
     * @param BunnyPullZone $pullzone
     * @return void
     */
    public function changePullZone(BunnyPullZone $pullzone): void
    {
        $this->pullzone = $pullzone;
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
            $this->write($destination, $this->read($source), new Config());
            // @codeCoverageIgnoreStart
        } catch (UnableToReadFile|UnableToWriteFile $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
        // @codeCoverageIgnoreEnd
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
            return new FileAttributes($this->getObject($path)->path(), null, $this->pullzone ? 'public' : 'private');
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
            // @codeCoverageIgnoreStart
        } catch (Exceptions\BunnyCDNException $e) {
            if (! str_contains($e->getMessage(), '404')) { // Urgh
                throw UnableToDeleteFile::atLocation($path, $e->getMessage());
            }
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @throws UnableToCheckExistence
     */
    public function directoryExists(string $path): bool
    {
        return $this->fileExists($path);
    }

    /**
     * @param  string  $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        $list = new DirectoryListing($this->listContents(
            Util::splitPathIntoDirectoryAndFile($path)['dir'],
            false
        ));

        $count = $list->filter(function (StorageAttributes $item) use ($path) {
            return Util::normalizePath($item->path()) === Util::normalizePath($path);
        })->toArray();

        return (bool) count($count);
    }

    /**
     * @param  string  $path
     * @param  Config  $config
     * @return string
     */
    public function checksum(string $path, Config $config): string
    {
        return $this->calculateChecksumFromStream($path, $config);
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
        if (!$this->pullzone) {
            throw new RuntimeException('In order to get a visible URL for a BunnyCDN object, a pullzone must passed to the BunnyCDNAdapter.');
        }

        return $this->pullzone->publicUrl($path);
    }

    /**
     * Builds a signed url for a BunnyCDN object
     *
     * @param string $path the path of the file to fetch
     * @param DateTimeInterface $expiresAt expiration date of the signed url
     * @param array $urlParameters optional parameters including: remote_ip, countries, & settings for bunny optimizer
     * @param string $allowForPath pass an optional path to enable access to related objects that may be in the same folder
     *
     * @return string
     *
     * @ref https://support.bunny.net/hc/en-us/articles/360016055099-How-to-sign-URLs-for-BunnyCDN-Token-Authentication
     *
     */
    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, array $urlParameters = [], string $allowForPath = ''): string
    {
        if (!$this->pullzone) {
            throw new RuntimeException('In order to get a public URL, a pullzone must passed to the BunnyCDNAdapter.');
        }
        return $this->pullzone->temporaryUrl($path, $expiresAt, $urlParameters, $allowForPath);
    }

    private static function parse_bunny_timestamp(string $timestamp): int
    {
        return (date_create_from_format('Y-m-d\TH:i:s.u', $timestamp) ?: date_create_from_format('Y-m-d\TH:i:s', $timestamp))->getTimestamp();
    }
}
