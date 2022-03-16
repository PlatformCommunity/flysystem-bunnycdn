<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\DirectoryNotEmptyException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\NotFoundException;

class BunnyCDNClient
{
    public $storage_zone_name;
    private $api_key;
    private $region;

    public $client;

    public function __construct(string $storage_zone_name, string $api_key, string $region = BunnyCDNRegion::FALKENSTEIN)
    {
        $this->storage_zone_name = $storage_zone_name;
        $this->api_key = $api_key;
        $this->region = $region;

        $this->client = new Guzzle();
    }

    private static function get_base_url($region): string
    {
        switch ($region) {
            case BunnyCDNRegion::NEW_YORK:
                return 'https://ny.storage.bunnycdn.com/';
            case BunnyCDNRegion::LOS_ANGELAS:
                return 'https://la.storage.bunnycdn.com/';
            case BunnyCDNRegion::SINGAPORE:
                return 'https://sg.storage.bunnycdn.com/';
            case BunnyCDNRegion::SYDNEY:
                return 'https://syd.storage.bunnycdn.com/';
            case BunnyCDNRegion::UNITED_KINGDOM:
                return 'https://uk.storage.bunnycdn.com/';
            case BunnyCDNRegion::STOCKHOLM:
                return 'https://se.storage.bunnycdn.com/';
            default:
                return 'https://storage.bunnycdn.com/';
        }
    }

    /**
     * @throws GuzzleException
     */
    private function request(string $path, string $method = 'GET', array $options = [])
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
            if($e->getCode() === 404) {
                throw new NotFoundException($e->getMessage());
            } else {
                throw new BunnyCDNException($e->getMessage());
            }
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
            if($e->getCode() === 404) {
                throw new NotFoundException($e->getMessage());
            } else {
                throw new BunnyCDNException($e->getMessage());
            }
        }
    }

    /**
     * @param string $path
     * @return resource|null
     * @throws BunnyCDNException
     * @throws NotFoundException
     */
    public function stream(string $path)
    {
        try {
            return $this->client->request(
                'GET',
                self::get_base_url($this->region) . Util::normalizePath('/' . $this->storage_zone_name . '/').$path,
                array_merge_recursive([
                    'stream' => true,
                    'headers' => [
                        'Accept' => '*/*',
                        'AccessKey' => $this->api_key, # Honestly... Why do I have to specify this twice... @BunnyCDN
                    ]
                ])
            )->getBody()->detach();
            // @codeCoverageIgnoreStart
        } catch (GuzzleException $e) {
            if($e->getCode() === 404) {
                throw new NotFoundException($e->getMessage());
            } else {
                throw new BunnyCDNException($e->getMessage());
            }
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param string $path
     * @param $contents
     * @return mixed
     * @throws BunnyCDNException
     */
    public function upload(string $path, $contents)
    {
        try {
            return $this->request($path, 'PUT', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                ],
                'body' => $contents
            ]);
        } catch (GuzzleException $e) {
            throw new BunnyCDNException($e->getMessage());
        }
    }

    /**
     * @param string $path
     * @return mixed
     * @throws BunnyCDNException
     */
    public function make_directory(string $path)
    {
        try {
            return $this->request(Util::normalizePath($path).'/', 'PUT', [
                'headers' => [
                    'Content-Length' => 0
                ],
            ]);
        } catch (GuzzleException $e) {
            if($e->getCode() === 400) {
                throw new BunnyCDNException('Directory already exists');
            } else {
                throw new BunnyCDNException($e->getMessage());
            }
        }
    }

    /**
     * @param string $path
     * @return mixed
     * @throws NotFoundException
     * @throws DirectoryNotEmptyException|BunnyCDNException
     */
    public function delete(string $path)
    {
        try {
            return $this->request($path, 'DELETE');
        } catch (GuzzleException $e) {
            if($e->getCode() === 404) {
                throw new NotFoundException($e->getMessage());
            } elseif($e->getCode() === 400) {
                throw new DirectoryNotEmptyException($e->getMessage());
            } else {
                throw new BunnyCDNException($e->getMessage());
            }
        }
    }
}