<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use Faker\Factory;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\Request;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\NotFoundException;
use PlatformCommunity\Flysystem\BunnyCDN\Util;

class MockClient extends BunnyCDNClient
{
    /**
     * @var Filesystem
     */
    public Filesystem $filesystem;

    public Guzzle $guzzleClient;

    public function __construct(string $storage_zone_name, string $api_key, string $region = '')
    {
        parent::__construct($storage_zone_name, $api_key, $region);
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    /**
     * @param  string  $path
     * @return array
     */
    public function list(string $path): array
    {
        try {
            return $this->filesystem->listContents($path)->map(function (StorageAttributes $file) {
                return ! $file instanceof FileAttributes
                    ? self::example_folder($file->path(), $this->storage_zone_name, [])
                    : self::example_file($file->path(), $this->storage_zone_name, [
                        'Length' => $file->fileSize(),
                        'Checksum' => hash('sha256', $this->filesystem->read($file->path())),
                    ]);
            })->toArray();
        } catch (FilesystemException) {
        }

        return [];
    }

    /**
     * @param  string  $path
     * @return string
     *
     * @throws FilesystemException
     */
    public function download(string $path): string
    {
        return $this->filesystem->read($path);
    }

    /**
     * @param  string  $path
     * @return resource
     *
     * @throws FilesystemException
     */
    public function stream(string $path)
    {
        return $this->filesystem->readStream($path);
    }

    /**
     * @param  string  $path
     * @param $contents
     * @return array
     */
    public function upload(string $path, $contents): array
    {
        try {
            $this->filesystem->write($path, $contents);

            return [
                'HttpCode' => 201,
                'Message' => 'File uploaded.',
            ];
        } catch (FilesystemException) {
        }

        return [];
    }

    /**
     * @param  string  $path
     * @return array
     */
    public function make_directory(string $path): array
    {
        try {
            $this->filesystem->createDirectory($path);

            return [
                'HttpCode' => 201,
                'Message' => 'Directory created.',
            ];
        } catch (FilesystemException) {
        }

        return [];
    }

    /**
     * @param  string  $path
     * @return array
     *
     * @throws FilesystemException
     * @throws BunnyCDNException
     * @throws NotFoundException
     */
    public function delete(string $path): array
    {
        try {
            $this->filesystem->has($path) ?
                $this->filesystem->deleteDirectory($path) || $this->filesystem->delete($path) :
                throw new NotFoundException();

            return [
                'HttpCode' => 200,
                'Message' => 'File deleted successfuly.', // ಠ_ಠ Spelling @bunny.net
            ];
        } catch (NotFoundException) {
            throw new NotFoundException('404');
        } catch (\Exception) {
            return [
                'HttpCode' => 404,
                'Message' => 'File deleted successfuly.', // ಠ_ಠ Spelling @bunny.net
            ];
        }
    }

    public function getUploadRequest(string $path, $contents): Request
    {
        return new Request('PUT', $path, [], $contents);
    }

    private static function example_file($path = '/directory/test.png', $storage_zone = 'storage_zone', $override = []): array
    {
        ['file' => $file, 'dir' => $dir] = Util::splitPathIntoDirectoryAndFile($path);
        $dir = Util::normalizePath($dir);
        $faker = Factory::create();

        return array_merge([
            'Guid' => $faker->uuid,
            'StorageZoneName' => $storage_zone,
            'Path' => Util::normalizePath('/'.$storage_zone.'/'.$dir.'/'),
            'ObjectName' => $file,
            'Length' => $faker->numberBetween(0, 10240),
            'LastChanged' => date('Y-m-d\TH:i:s.v'),
            'ServerId' => $faker->numberBetween(0, 10240),
            'ArrayNumber' => 0,
            'IsDirectory' => false,
            'UserId' => 'bf91bc4e-0e60-411a-b475-4416926d20f7',
            'ContentType' => '',
            'DateCreated' => date('Y-m-d\TH:i:s.v'),
            'StorageZoneId' => $faker->numberBetween(0, 102400),
            'Checksum' => strtoupper($faker->sha256),
            'ReplicatedZones' => '',
        ], $override);
    }

    private static function example_folder($path = '/directory/', $storage_zone = 'storage_zone', $override = []): array
    {
        ['file' => $file, 'dir' => $dir] = Util::splitPathIntoDirectoryAndFile($path);
        $dir = Util::normalizePath($dir);
        $faker = Factory::create();

        return array_merge([
            'Guid' => $faker->uuid,
            'StorageZoneName' => $storage_zone,
            'Path' => Util::normalizePath('/'.$storage_zone.'/'.$dir.'/'),
            'ObjectName' => $file,
            'Length' => 0,
            'LastChanged' => date('Y-m-d\TH:i:s.v'),
            'ServerId' => $faker->numberBetween(0, 10240),
            'ArrayNumber' => 0,
            'IsDirectory' => true,
            'UserId' => 'bf91bc4e-0e60-411a-b475-4416926d20f7',
            'ContentType' => '',
            'DateCreated' => date('Y-m-d\TH:i:s.v'),
            'StorageZoneId' => $faker->numberBetween(0, 102400),
            'Checksum' => '',
            'ReplicatedZones' => '',
        ], $override);
    }
}
