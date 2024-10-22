<?php

namespace PlatformCommunity\Flysystem\BunnyCDN;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\NotFoundException;
use Psr\Http\Client\ClientExceptionInterface;

class BunnyCDNClient
{
    public Guzzle $guzzleClient;

    public function __construct(
        public string $storage_zone_name,
        private string $api_key,
        private string $region = BunnyCDNRegion::FALKENSTEIN
    ) {
        $this->guzzleClient = new Guzzle();
    }

    private static function get_base_url($region): string
    {
        return match (strtolower($region)) {
            BunnyCDNRegion::NEW_YORK => 'https://ny.storage.bunnycdn.com/',
            BunnyCDNRegion::LOS_ANGELAS => 'https://la.storage.bunnycdn.com/',
            BunnyCDNRegion::SINGAPORE => 'https://sg.storage.bunnycdn.com/',
            BunnyCDNRegion::SYDNEY => 'https://syd.storage.bunnycdn.com/',
            BunnyCDNRegion::UNITED_KINGDOM => 'https://uk.storage.bunnycdn.com/',
            BunnyCDNRegion::STOCKHOLM => 'https://se.storage.bunnycdn.com/',
            BunnyCDNRegion::BRAZIL => 'https://br.storage.bunnycdn.com/',
            BunnyCDNRegion::JOHANNESBURG => 'https://jh.storage.bunnycdn.com/',
            default => 'https://storage.bunnycdn.com/'
        };
    }

    public function createRequest(string $path, string $method = 'GET', array $headers = [], $body = null): Request
    {
        return new Request(
            $method,
            self::get_base_url($this->region).Util::normalizePath('/'.$this->storage_zone_name.'/').$path,
            array_merge([
                'Accept' => '*/*',
                'AccessKey' => $this->api_key,
            ], $headers),
            $body
        );
    }

    /**
     * @throws ClientExceptionInterface
     */
    private function request(Request $request, array $options = []): mixed
    {
        $contents = $this->guzzleClient->send($request, $options)->getBody()->getContents();

        return json_decode($contents, true) ?? $contents;
    }

    /**
     * @param  string  $path
     * @return array
     *
     * @throws NotFoundException|BunnyCDNException
     */
    public function list(string $path): array
    {
        try {
            $listing = $this->request($this->createRequest(Util::normalizePath($path).'/'));

            // Throw an exception if we don't get back an array
            if (! is_array($listing)) {
                throw new NotFoundException('File is not a directory');
            }

            return array_map(function ($bunny_cdn_item) {
                return $bunny_cdn_item;
            }, $listing);
            // @codeCoverageIgnoreStart
        } catch (GuzzleException $e) {
            throw match ($e->getCode()) {
                404 => new NotFoundException($e->getMessage()),
                default => new BunnyCDNException($e->getMessage())
            };
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param  string  $path
     * @return mixed
     *
     * @throws BunnyCDNException
     * @throws NotFoundException
     */
    public function download(string $path): string
    {
        try {
            $content = $this->request($this->createRequest($path.'?download'));

            if (\is_array($content)) {
                return \json_encode($content);
            }

            return $content;
            // @codeCoverageIgnoreStart
        } catch (GuzzleException $e) {
            throw match ($e->getCode()) {
                404 => new NotFoundException($e->getMessage()),
                default => new BunnyCDNException($e->getMessage())
            };
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param  string  $path
     * @return resource|null
     *
     * @throws BunnyCDNException
     * @throws NotFoundException
     */
    public function stream(string $path)
    {
        try {
            return $this->guzzleClient->send($this->createRequest($path), ['stream' => true])->getBody()->detach();
            // @codeCoverageIgnoreStart
        } catch (GuzzleException $e) {
            throw match ($e->getCode()) {
                404 => new NotFoundException($e->getMessage()),
                default => new BunnyCDNException($e->getMessage())
            };
        }
        // @codeCoverageIgnoreEnd
    }

    public function getUploadRequest(string $path, $contents): Request
    {
        return $this->createRequest(
            $path,
            'PUT',
            [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            ],
            $contents
        );
    }

    /**
     * @param  string  $path
     * @param $contents
     * @return mixed
     *
     * @throws BunnyCDNException
     */
    public function upload(string $path, $contents): mixed
    {
        try {
            return $this->request($this->getUploadRequest($path, $contents));
            // @codeCoverageIgnoreStart
        } catch (GuzzleException $e) {
            throw new BunnyCDNException($e->getMessage());
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param  string  $path
     * @return mixed
     *
     * @throws BunnyCDNException
     */
    public function make_directory(string $path): mixed
    {
        try {
            return $this->request($this->createRequest(Util::normalizePath($path).'/', 'PUT', [
                'Content-Length' => 0,
            ]));
            // @codeCoverageIgnoreStart
        } catch (GuzzleException $e) {
            throw match ($e->getCode()) {
                400 => new BunnyCDNException('Directory already exists'),
                default => new BunnyCDNException($e->getMessage())
            };
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param  string  $path
     * @return mixed
     *
     * @throws NotFoundException
     * @throws BunnyCDNException
     */
    public function delete(string $path): mixed
    {
        try {
            return $this->request($this->createRequest($path, 'DELETE'));
            // @codeCoverageIgnoreStart
        } catch (GuzzleException $e) {
            throw match ($e->getCode()) {
                404 => new NotFoundException($e->getMessage()),
                default => new BunnyCDNException($e->getMessage())
            };
        }
        // @codeCoverageIgnoreEnd
    }
}
