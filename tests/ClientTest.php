<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use GuzzleHttp\Psr7\Response;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\NotFoundException;

class ClientTest extends TestCase
{
    const STORAGE_ZONE = 'example_storage_zone';

    public $client;

    protected function setUp(): void
    {
        $this->client = new MockClient(self::STORAGE_ZONE, 'api-key');
    }

    public function test_listing_directory()
    {
        if($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(200, [], json_encode(
                    [
                        $this->client::example_folder('subfolder', self::STORAGE_ZONE),
                        $this->client::example_file('example_image.png', self::STORAGE_ZONE)
                    ]
                ))
            );
        }

        $response = $this->client->list('/');

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
    }

    public function test_listing_subdirectory()
    {
        if($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(200, [], json_encode(
                    [
                        $this->client::example_file('/subfolder/example_image.png', self::STORAGE_ZONE)
                    ]
                ))
            );
        }

        $response = $this->client->list('/subfolder');

        $this->assertIsArray($response);
        $this->assertCount(1, $response);
    }

    public function test_download_file()
    {
        if($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(200, [], 'example_image_contents')
            );
        }

        $response = $this->client->download('/test.png');

        $this->assertIsString($response);
    }

    public function test_upload()
    {
        if($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(201, [], json_encode([
                    'HttpCode'  => 201,
                    'Message'   => 'File uploaded.'
                ]))
            );
        }

        $response = $this->client->upload('/test_contents.txt', 'testing_contents');

        $this->assertIsArray($response);
        $this->assertEquals([
            'HttpCode'  => 201,
            'Message'   => 'File uploaded.'
        ], $response);
    }

    public function test_make_directory()
    {
        if($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(201, [], json_encode([
                    'HttpCode' => 201,
                    'Message' => 'Directory created.'
                ]))
            );
        }

        $response = $this->client->make_directory('/test_dir/');

        $this->assertIsArray($response);
        $this->assertEquals([
            'HttpCode' => 201,
            'Message' => 'Directory created.'
        ], $response);
    }

    public function test_delete_file()
    {
        if($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(200, [], json_encode([
                    'HttpCode' => 200,
                    'Message' => 'File deleted successfuly.'
                ]))
            );
        }

        $response = $this->client->delete('/testin.txt');

        $this->assertIsArray($response);
        $this->assertEquals([
            'HttpCode' => 200,
            'Message' => 'File deleted successfuly.'
        ], $response);
    }

    public function test_delete_file_not_found()
    {
        $this->expectException(NotFoundException::class);

        if($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(404, [], json_encode([
                    'HttpCode' => 404,
                    'Message' => 'Object Not Found.'
                ]))
            );
        }

        $this->client->delete('/test.txt');
    }
}
