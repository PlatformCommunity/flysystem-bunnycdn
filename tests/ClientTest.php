<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;

class ClientTest extends TestCase
{
    public function test_example()
    {
       $client = new BunnyCDNClient('testing1827129361', 'b0e98a1b-d62d-4c31-aae0df94bbf6-1592-4f66');
       var_dump($client->download('/test.png'));
    }
}
