<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\DirectoryNotEmptyException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\NotFoundException;

class BunnyCDNClient
{
    private string $storage_zone_name;
    private string $api_key;

    private Guzzle $client;

    public function __construct(string $storage_zone_name, string $api_key, string $region = '')
    {
        $this->storage_zone_name = $storage_zone_name;
        $this->api_key = $api_key;

        $this->client = new Guzzle();
    }

    /**
     * @throws GuzzleException
     */
    private function request(string $path, string $method = 'GET', array $options = [], $data = null): mixed
    {
        $response = $this->client->request($method, 'https://storage.bunnycdn.com/' . Util::normalizePath('/' . $this->storage_zone_name . '/'. $path), [
            'headers' => array_merge([
                'Accept' => '*/*',
                'AccessKey' => $this->api_key,
            ], $options),
            'file' => $data
        ]);

        return json_decode($response->getBody()->getContents()) ?? $response->getBody()->getContents();
    }

    /**
     * @param string $path
     * @return mixed
     * @throws NotFoundException|BunnyCDNException
     */
    public function list(string $path): mixed
    {
        try {
            return $this->request($path);
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
     * @throws NotFoundException|BunnyCDNException
     */
    public function download(string $path): mixed
    {
        try {
            return $this->request($path);
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
                'Content-Type' => 'application/octet-stream',
            ], base64_encode($contents));
        } catch (GuzzleException $e) {
            throw match ($e->getCode()) {
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