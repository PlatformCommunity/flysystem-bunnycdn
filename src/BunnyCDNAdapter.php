<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Adapter\Polyfill\StreamedCopyTrait;
use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\Adapter\Polyfill\StreamedWritingTrait;
use League\Flysystem\Config;
use League\Flysystem\Exception;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\UnreadableFileException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\NotFoundException;
use RuntimeException;
use stdClass;

class BunnyCDNAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;
    use StreamedCopyTrait;
    use StreamedWritingTrait;

    /**
     * Pull Zone URL
     * @var string
     */
    private $pullzone_url;

    /**
     * @var BunnyCDNClient
     */
    private $client;

    /**
     * @var string
     */
    private $prefixPath;

    /**
     * @param BunnyCDNClient $client
     * @param string $pullzone_url
     * @param string $prefixPath
     */
    public function __construct(BunnyCDNClient $client, string $pullzone_url = '', string $prefixPath = '')
    {
        $this->client = $client;
        $this->pullzone_url = $pullzone_url;
        $this->prefixPath = $prefixPath;
    }

    /**
     * @param $path
     * @param $contents
     * @param Config $config
     * @return bool
     */
    public function write($path, $contents, Config $config): bool
    {
        $path = $this->prependPrefix($path);

        try {
            $this->client->upload($path, $contents);
            // @codeCoverageIgnoreStart
        } catch (Exceptions\BunnyCDNException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        return true;
    }

    /**
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @return bool|false
     */
    public function update($path, $contents, Config $config): bool
    {
        $path = $this->prependPrefix($path);
        return $this->write($path, $contents, $config);
    }

    /**
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function rename($path, $newpath): bool
    {
        $path = $this->prependPrefix($path);
        $newpath = $this->prependPrefix($newpath);

        try {
            $this->copy($path, $newpath);
            $this->delete($path);
            // @codeCoverageIgnoreStart
        } catch (BunnyCDNException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd
        return true;
    }

    /**
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function copy($path, $newpath): bool
    {
        $path = $this->prependPrefix($path);
        $newpath = $this->prependPrefix($newpath);

        try {
            $this->write($newpath, $this->read($path)['contents'], new Config());
            // @codeCoverageIgnoreStart
        } catch (BunnyCDNException $exception) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        return true;
    }

    /**
     * @param string $path
     * @return bool
     */
    public function delete($path): bool
    {
        $path = $this->prependPrefix($path);

        try {
            $this->client->delete($path);
        } catch (Exceptions\BunnyCDNException $e) {
            if(strpos($e->getMessage(), '404') === false) { # Urgh
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $dirname
     * @return bool
     */
    public function deleteDir($dirname): bool
    {
        $dirname = $this->prependPrefix($dirname);

        try {
            $this->client->delete(
                rtrim($dirname, '/') . '/'
            );
            // @codeCoverageIgnoreStart
        } catch (Exceptions\BunnyCDNException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd
        return true;
    }

    /**
     * @param string $dirname
     * @param Config $config
     * @return bool
     */
    public function createDir($dirname, Config $config): bool
    {
        $dirname = $this->prependPrefix($dirname);

        try {
            $this->client->make_directory($dirname);
        } catch (Exceptions\BunnyCDNException $e) {
            # Lol apparently this is "idempotent" but there's an exception... Sure whatever..
            if ($e->getMessage() !== 'Directory already exists') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $path
     * @return bool
     * @throws BunnyCDNException
     * @throws BunnyCDNException
     */
    public function has($path): bool
    {
        $path = $this->prependPrefix($path);

        return $this->getMetadata($path) !== false;
    }

    /**
     * @param string $path
     * @return array|false
     */
    public function read($path)
    {
        $path = $this->prependPrefix($path);

        try {
            return array_merge($this->getMetadata($path) ?: [], [
                'contents' => $this->client->download($path)
            ]);
            // @codeCoverageIgnoreStart
        } catch (Exceptions\BunnyCDNException $e) {
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Reads a file as a stream.
     * @param string $path
     * @return array|false
     */
    public function readStream($path)
    {
        $path = $this->prependPrefix($path);

        try {
            return [
                'stream' => $this->client->stream($path)
            ];
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string $directory
     * @param bool $recursive
     * @return array|false
     */
    public function listContents($directory = '', $recursive = false)
    {
        $directory = $this->prependPrefix($directory);
        $contents = $this->listContentsHelper($directory, $recursive);
        if ($contents === false) {
            return false;
        }

        return \iterator_to_array($contents);
    }

    private function listContentsHelper($directory = '', $recursive = false) {
        $directory = $this->prependPrefix($directory);

        try {
            $entries = $this->client->list($directory);
        } catch (Exceptions\BunnyCDNException $e) {
            return false;
        }

        foreach ($entries as $item) {
            $content = $this->normalizeObject($item);
            yield $content;

            if ($recursive && $content['type'] === 'dir') {
                foreach ($this->listContentsHelper($content['path'], $recursive) as $deepItem) {
                    yield $deepItem;
                }
            }
        }
    }

    /**
     * @param $path
     * @return void
     */
    protected function getObject($path)
    {
    }

    /**
     * @param array $bunny_file_array
     * @return array
     */
    protected function normalizeObject(array $bunny_file_array): array
    {
        return [
            'type'      => $bunny_file_array['IsDirectory'] ? 'dir' : 'file',
            'dirname'   => rtrim(Util::normalizePath(
                str_replace(
                    $bunny_file_array['StorageZoneName'] . '/',
                    '/',
                    $bunny_file_array['Path']
                )
            ), '/'),
            'mimetype'  => $bunny_file_array['ContentType'],
            'guid' => $bunny_file_array['Guid'],
            'path'      => '/'.Util::normalizePath(
                str_replace(
                    $bunny_file_array['StorageZoneName'] . '/',
                    '/',
                    $bunny_file_array['Path'] . $bunny_file_array['ObjectName']
                )
            ),
            'object_name' => $bunny_file_array['ObjectName'],
            'size'      => $bunny_file_array['Length'],
            'timestamp' => self::parse_bunny_timestamp($bunny_file_array['LastChanged']),
            'server_id' => $bunny_file_array['ServerId'],
            'user_id' => $bunny_file_array['UserId'],
            'last_changed' => self::parse_bunny_timestamp($bunny_file_array['LastChanged']),
            'date_created' => self::parse_bunny_timestamp($bunny_file_array['DateCreated']),
            'storage_zone_name' => $bunny_file_array['StorageZoneName'],
            'storage_zone_id' => $bunny_file_array['StorageZoneId'],
            'checksum' => $bunny_file_array['Checksum'],
            'replicated_zones' => $bunny_file_array['ReplicatedZones'],
        ];
    }

    /**
     * Returns a normalised Flysystem Metadata Array
     * * array_values is called because array_filter sometimes doesn't start the array at 1
     *
     * @param string $path
     * @return array|false
     * @throws BunnyCDNException
     */
    public function getMetadata($path)
    {
        $path = $this->prependPrefix($path);
        $list = array_values(array_filter($this->listContents(
            Util::splitPathIntoDirectoryAndFile(
                Util::normalizePath($path)
            )['dir']
        ) ?: [], function($item) use ($path) {
            return Util::normalizePath($item['path']) === Util::normalizePath($path);
        }));

        if (count($list) === 1) {
            return $list[0];
        }

        return false;
    }

    /**
     * @param string $path
     * @return array
     * @throws BunnyCDNException
     * @throws BunnyCDNException
     */
    public function getSize($path)
    {
        $path = $this->prependPrefix($path);
        return $this->getMetadata($path);
    }

    /**
     * @codeCoverageIgnore
     * @param string $path
     * @return array
     * @throws BunnyCDNException
     * @throws BunnyCDNException
     */
    public function getMimetype($path)
    {
        $path = $this->prependPrefix($path);
        return $this->getMetadata($path);
    }

    /**
     * @param string $path
     * @return array
     * @throws BunnyCDNException
     * @throws BunnyCDNException
     */
    public function getTimestamp($path)
    {
        $path = $this->prependPrefix($path);
        return $this->getMetadata($path);
    }

    /**
     * getURL method for Laravel users who want to use BunnyCDN's PullZone to retrieve a public URL
     * @param string $path
     * @return string
     */
    public function getUrl(string $path): string
    {
        $path = $this->prependPrefix($path);
        if ($this->pullzone_url === '') {
            throw new RuntimeException('In order to get a visible URL for a BunnyCDN object, you must pass the "pullzone_url" parameter to the BunnyCDNAdapter.');
        }

        return rtrim($this->pullzone_url, '/') . '/' . ltrim($path, '/');
    }

    private static function parse_bunny_timestamp(string $timestamp): int
    {
        return (date_create_from_format('Y-m-d\TH:i:s.u', $timestamp) ?: date_create_from_format('Y-m-d\TH:i:s', $timestamp))->getTimestamp();
    }

    private function prependPrefix(string $path): string
    {
        if ($this->prefixPath === '') {
            return $path;
        }

        if ($path === $this->prefixPath) {
            return $path;
        }

        if (\strpos($path, $this->prefixPath . '/') === 0) {
            return $path;
        }

        return $this->prefixPath . '/' . $path;
    }
}
