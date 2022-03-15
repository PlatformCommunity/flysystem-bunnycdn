<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\DirectoryNotEmptyException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\NotFoundException;

class BunnyCDNClient
{
    public string $storage_zone_name;
    private string $api_key;
    private string $region;

    public Guzzle $client;

    public function __construct(string $storage_zone_name, string $api_key, string $region = BunnyCDNRegion::FALKENSTEIN)
    {
        $this->storage_zone_name = $storage_zone_name;
        $this->api_key = $api_key;
        $this->region = $region;

        $this->client = new Guzzle();
    }

    private static function get_base_url($region): string
    {
        return match($region) {
            'ny' => 'https://ny.storage.bunnycdn.com/',
            'la' => 'https://la.storage.bunnycdn.com/',
            'sg' => 'https://sg.storage.bunnycdn.com/',
            'syd' => 'https://syd.storage.bunnycdn.com/',
            'uk' => 'https://uk.storage.bunnycdn.com/',
            default => 'https://storage.bunnycdn.com/'
        };
    }

    /**
     * @throws GuzzleException
     */
    private function request(string $path, string $method = 'GET', array $options = []): mixed
    {
        $response = $this->client->request(
            $method,
            self::get_base_url($this->region) . Util::normalizePath('/' . $this->storage_zone_name . '/').$path,
            array_merge_recursive([
                'headers' => [
                    'Accept' => '*/*',
                    'AccessKey' => $this->api_key, # Honestly... Why do I have to specify this twice... @BunnyCDN
                ],
            ], $options)
        );

        $contents = $response->getBody()->getContents();

        return json_decode($contents, true) ?? $contents;
    }

    /**
     * @param string $path
     * @return array
     * @throws NotFoundException|BunnyCDNException
     */
    public function list(string $path): array
    {
        try {
            $listing = $this->request(Util::normalizePath($path).'/');

            # Throw an exception if we don't get back an array
            if(!is_array($listing)) { throw new NotFoundException('File is not a directory'); }

            return array_map(function($bunny_cdn_item) {
                return $bunny_cdn_item;
            }, $listing);
        } catch (GuzzleException $e) {
            throw match ($e->getCode()) {
                404 => new NotFoundException($e->getMessage()),
                default => new BunnyCDNException($e->getMessage())
            };
        }
    }


    /**
     * @param string $path
     * @return mixed
     * @throws BunnyCDNException
     * @throws NotFoundException
     */
    public function download(string $path): string
    {
        try {
            return $this->request($path . '?download');
        } catch (GuzzleException $e) {
            throw match ($e->getCode()) {
                404 => new NotFoundException($e->getMessage()),
                default => new BunnyCDNException($e->getMessage())
            };
        }
    }

    /**
     * @param string $path
     * @param $contents
     * @return mixed
     * @throws BunnyCDNException
     */
    public function upload(string $path, $contents): mixed
    {
        try {
            return $this->request($path, 'PUT', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                ],
                'body' => $contents
            ]);
        } catch (GuzzleException $e) {
            throw match ($e->getCode()) {
                default => new BunnyCDNException($e->getMessage())
            };
        }
    }

    /**
     * @param string $path
     * @return mixed
     * @throws BunnyCDNException
     */
    public function make_directory(string $path): mixed
    {
        try {
            return $this->request(Util::normalizePath($path).'/', 'PUT', [
                'headers' => [
                    'Content-Length' => 0
                ],
            ]);
        } catch (GuzzleException $e) {
            throw match ($e->getCode()) {
                400 => new BunnyCDNException('Directory already exists'),
                default => new BunnyCDNException($e->getMessage())
            };
        }
    }

    /**
     * @param string $path
     * @return mixed
     * @throws NotFoundException
     * @throws DirectoryNotEmptyException|BunnyCDNException
     */
    public function delete(string $path): mixed
    {
        try {
            return $this->request($path, 'DELETE');
        } catch (GuzzleException $e) {
            throw match ($e->getCode()) {
                404 => new NotFoundException($e->getMessage()),
                400 => new DirectoryNotEmptyException($e->getMessage()),
                default => new BunnyCDNException($e->getMessage())
            };
        }
    }
}
