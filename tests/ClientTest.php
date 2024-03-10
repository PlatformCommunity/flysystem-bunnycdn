<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use function PHPUnit\Framework\assertEmpty;
use PHPUnit\Framework\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\NotFoundException;

if (\is_file(__DIR__.'/ClientDI.php')) {
    require_once __DIR__.'/ClientDI.php';
}

class ClientTest extends TestCase
{
    public BunnyCDNClient $client;

    private static function bunnyCDNClient(): BunnyCDNClient
    {
        global $storage_zone;
        global $api_key;
        global $region;

        if ($storage_zone !== null && $api_key !== null) {
            return new BunnyCDNClient($storage_zone, $api_key, $region ?? BunnyCDNRegion::DEFAULT);
        }

        return new MockClient('test_storage_zone', '123');
    }

    protected function setUp(): void
    {
        $this->client = self::bunnyCDNClient();
        $this->clearStorage();
    }

    private function clearStorage()
    {
        foreach ($this->client->list('/') as $item) {
            try {
                $this->client->delete($item['IsDirectory'] ? $item['ObjectName'].'/' : $item['ObjectName']);
            } catch (\Exception $exception) {
            } // Try our best effort at removing everything from the filesystem
        }

        assertEmpty(
            $this->client->list('/'),
            'Warning! Bunny Client not emptied out prior to next test. This can be problematic when running the test against production clients'
        );
    }

    protected function tearDown(): void
    {
        $this->clearStorage();
    }

    /**
     * @return void
     *
     * @throws NotFoundException
     * @throws BunnyCDNException
     */
    public function test_listing_directory()
    {
        // Arrange
        $this->client->make_directory('subfolder');
        $this->client->upload('example_image.png', 'test');

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
        // Arrange
        $this->client->upload('/subfolder/example_image.png', 'test');

        // Act
        $response = $this->client->list('/subfolder');

        // Assert
        $this->assertIsArray($response);
        $this->assertCount(1, $response);
    }

    /**
     * @return void
     *
     * @throws BunnyCDNException
     * @throws NotFoundException
     */
    public function test_download_file()
    {
        $this->client->upload('/test.png', 'test');

        $response = $this->client->download('/test.png');

        $this->assertIsString($response);
    }

    /**
     * @return void
     *
     * @throws BunnyCDNException
     * @throws NotFoundException
     */
    public function test_streaming()
    {
        $this->client->upload('/test.png', str_repeat('example_image_contents', 1024));

        $stream = $this->client->stream('/test.png');

        $this->assertIsResource($stream);

        do {
            $line = stream_get_line($stream, 512);
            $this->assertStringContainsString('example_image_contents', $line);
            $this->assertEquals(512, strlen($line));
        } while ($line && strlen($line) > 512);
    }

    /**
     * @return void
     *
     * @throws BunnyCDNException
     */
    public function test_upload()
    {
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
     * @throws BunnyCDNException
     * @throws NotFoundException
     */
    public function test_delete_file()
    {
        $this->client->upload('test_file.txt', '123');

        $response = $this->client->delete('/test_file.txt');

        $this->assertIsArray($response);
        $this->assertEquals([
            'HttpCode' => 200,
            'Message' => 'File deleted successfuly.', // ಠ_ಠ Spelling @bunny.net
        ], $response);
    }

    /**
     * @return void
     *
     * @throws BunnyCDNException
     * @throws NotFoundException
     */
    public function test_delete_file_not_found()
    {
        $this->expectException(NotFoundException::class);
        $this->client->delete('file_not_found.txt');
    }
}
