<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\DirectoryNotEmptyException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\NotFoundException;

class ClientTest extends TestCase
{
    const STORAGE_ZONE = 'example_storage_zone';

    public BunnyCDNClient|MockClient $client;

    protected function setUp(): void
    {
        $this->client = new MockClient(self::STORAGE_ZONE, 'b0e98a1b-d62d-4c31-aae0df94bbf6-1592-4f66');
    }

    /**
     * @return void
     *
     * @throws NotFoundException
     * @throws BunnyCDNException
     */
    public function test_listing_directory()
    {
        if ($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(200, [], json_encode(
                    [
                        $this->client::example_folder('subfolder', self::STORAGE_ZONE),
                        $this->client::example_file('example_image.png', self::STORAGE_ZONE),
                    ]
                ))
            );
        }

        $response = $this->client->list('/');

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
    }

    /**
     * @return void
     *
     * @throws NotFoundException
     * @throws BunnyCDNException
     */
    public function test_listing_subdirectory()
    {
        if ($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(200, [], json_encode(
                    [
                        $this->client::example_file('/subfolder/example_image.png', self::STORAGE_ZONE),
                    ]
                ))
            );
        }

        $response = $this->client->list('/subfolder');

        $this->assertIsArray($response);
        $this->assertCount(1, $response);
    }

    /**
     * @return void
     *
     * @throws NotFoundException
     * @throws BunnyCDNException
     */
    public function test_download_file()
    {
        if ($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(200, [], 'example_image_contents')
            );
        }

        $response = $this->client->download('/test.png');

        $this->assertIsString($response);
    }

    /**
     * @return void
     *
     * @throws NotFoundException
     * @throws BunnyCDNException
     */
    public function test_streaming()
    {
        if ($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(200, [], str_repeat('example_image_contents', 1024000)),
            );
        }

        $stream = $this->client->stream('/test.png');

        $this->assertIsResource($stream);

        do {
            $line = stream_get_line($stream, 512);
            $this->assertStringContainsString('example_image_contents', $line);
            $this->assertEquals(512, strlen($line));
            break;
        } while ($line);
    }

    /**
     * @return void
     *
     * @throws BunnyCDNException
     */
    public function test_upload()
    {
        if ($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(201, [], json_encode([
                    'HttpCode' => 201,
                    'Message' => 'File uploaded.',
                ]))
            );
        }

        $response = $this->client->upload('/test_contents.txt', 'testing_contents');

        $this->assertIsArray($response);
        $this->assertEquals([
            'HttpCode' => 201,
            'Message' => 'File uploaded.',
        ], $response);
    }

    /**
     * @return void
     *
     * @throws BunnyCDNException
     */
    public function test_make_directory()
    {
        if ($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(201, [], json_encode([
                    'HttpCode' => 201,
                    'Message' => 'Directory created.',
                ]))
            );
        }

        $response = $this->client->make_directory('/test_dir/');

        $this->assertIsArray($response);
        $this->assertEquals([
            'HttpCode' => 201,
            'Message' => 'Directory created.',
        ], $response);
    }

    /**
     * @return void
     *
     * @throws NotFoundException
     * @throws BunnyCDNException
     * @throws DirectoryNotEmptyException
     */
    public function test_delete_file()
    {
        if ($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(200, [], json_encode([
                    'HttpCode' => 200,
                    'Message' => 'File deleted successfuly.', // ಠ_ಠ Spelling @bunny.net
                ]))
            );
        }

        $response = $this->client->delete('/testing.txt');

        $this->assertIsArray($response);
        $this->assertEquals([
            'HttpCode' => 200,
            'Message' => 'File deleted successfuly.', // ಠ_ಠ Spelling @bunny.net
        ], $response);
    }

    /**
     * @return void
     *
     * @throws NotFoundException
     * @throws BunnyCDNException
     * @throws DirectoryNotEmptyException
     */
    public function test_delete_file_not_found()
    {
        $this->expectException(NotFoundException::class);

        if ($this->client instanceof MockClient) {
            $this->client->add_response(
                new Response(404, [], json_encode([
                    'HttpCode' => 404,
                    'Message' => 'Object Not Found.',
                ]))
            );
        }

        $this->client->delete('/file_not_found.txt');
    }

    /**
     * Utility Classes
     */

//    /**
//     * @param $path
//     * @param $contents
//     * @return void
//     * @throws BunnyCDNException
//     */
//    private function givenWeHaveAnExistingFile($path = '/example_file', $contents = ''): void
//    {
//        if($this->client instanceof MockClient) {
//            $this->client->add_response(
//                new Response(200, [], json_encode(
//                    [
//                        $this->client::example_file($path, self::STORAGE_ZONE)
//                    ]
//                ))
//            );
//        } else {
//            $this->client->upload($path, $contents);
//        }
//    }
//
//    /**
//     * @param $path
//     * @return void
//     * @throws BunnyCDNException
//     */
//    public function givenWeHaveAnExistingFolder($path = '/example_folder'): void
//    {
//        if($this->client instanceof MockClient) {
//            $this->client->add_response(
//                new Response(200, [], json_encode(
//                    [
//                        $this->client::example_folder($path, self::STORAGE_ZONE)
//                    ]
//                ))
//            );
//        } else {
//            $this->client->make_directory($path);
//        }
//    }
}
