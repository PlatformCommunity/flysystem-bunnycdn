<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use Faker\Factory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\Util;

class MockClient extends BunnyCDNClient
{
    public $mock;

    public function __construct(string $storage_zone_name, string $api_key, string $region = '')
    {
        parent::__construct($storage_zone_name, $api_key, $region);

        $this->mock = new MockHandler([ ]);
        $handlerStack = HandlerStack::create($this->mock);

        $this->client = new Client(['handler' => $handlerStack]);
    }

    public function add_response(Response $response)
    {
        return $this->mock->append($response);
    }

    public static function example_file($path = '/directory/test.png', $storage_zone = 'storage_zone', $override = []): array
    {
        list('file' => $file, 'dir' => $dir) = Util::splitPathIntoDirectoryAndFile($path);
        $dir = Util::normalizePath($dir);
        $faker = Factory::create();

        return array_merge([
            'Guid' => $faker->uuid,
            'StorageZoneName' => $storage_zone,
            'Path' => Util::normalizePath("/" . $storage_zone . '/' . $dir . "/"),
            'ObjectName' => $file,
            'Length' => $faker->numberBetween(0, 10240),
            'LastChanged' => date('Y-m-d\TH:i:s.v'),
            'ServerId' => $faker->numberBetween(0, 10240),
            'ArrayNumber' => 0,
            'IsDirectory' => false,
            'UserId' => "bf91bc4e-0e60-411a-b475-4416926d20f7",
            'ContentType' => "",
            'DateCreated' => date('Y-m-d\TH:i:s.v'),
            'StorageZoneId' => $faker->numberBetween(0, 102400),
            'Checksum' => strtoupper($faker->sha256),
            'ReplicatedZones' => "",
        ], $override);
    }

    public static function example_folder($path = '/directory/', $storage_zone = 'storage_zone', $override = []): array
    {
        list('file' => $file, 'dir' => $dir) = Util::splitPathIntoDirectoryAndFile($path);
        $dir = Util::normalizePath($dir);
        $faker = Factory::create();

        return array_merge([
            'Guid' => $faker->uuid,
            'StorageZoneName' => $storage_zone,
            'Path' => Util::normalizePath("/" . $storage_zone . '/' . $dir . "/"),
            'ObjectName' => $file,
            'Length' => 0,
            'LastChanged' => date('Y-m-d\TH:i:s.v'),
            'ServerId' => $faker->numberBetween(0, 10240),
            'ArrayNumber' => 0,
            'IsDirectory' => true,
            'UserId' => "bf91bc4e-0e60-411a-b475-4416926d20f7",
            'ContentType' => "",
            'DateCreated' => date('Y-m-d\TH:i:s.v'),
            'StorageZoneId' => $faker->numberBetween(0, 102400),
            'Checksum' => '',
            'ReplicatedZones' => "",
        ], $override);
    }
}