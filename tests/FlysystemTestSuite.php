<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use Exception;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Memory\MemoryAdapter;
use League\Flysystem\UnreadableFileException;
use Mockery;
use PHPUnit\Framework\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;
use PlatformCommunity\Flysystem\BunnyCDN\Exceptions\BunnyCDNException;
use Prophecy\Argument;
use RuntimeException;

class FlysystemTestSuite extends TestCase
{
    const STORAGE_ZONE = 'testing_storage_zone';

    const TEST_FILE_CONTENTS = 'example_testing_contents';

    const PULLZONE_URL = 'https://testing.b-cdn.net/';

    /**
     * The memory adapter;
     */
    protected $adapter;

    /**
     * @var AdapterInterface|null
     */
    protected $prefixAdapter;

    /**
     * @var BunnyCDNClient|null
     */
    protected static $bunnyCDNClient;

    /**
     * @var string|null
     */
    protected static $prefixPath;

    protected function setUp(): void
    {
        $this->adapter = self::createFilesystemAdapter();
        $this->prefixAdapter = self::createPrefixFilesystemAdapter();
    }

    public static function tearDownAfterClass(): void
    {
        $this->adapter->deleteDir('/');
    }

    public static function setUpBeforeClass(): void
    {
        static::$prefixPath = 'test'.bin2hex(random_bytes(10));
    }

    /**
     * @param string $prefixPath
     * @return BunnyCDNAdapter
     */
    protected static function createFilesystemAdapter(string $prefixPath = ''): BunnyCDNAdapter
    {
        return new BunnyCDNAdapter(static::bunnyCDNClient(), self::PULLZONE_URL, $prefixPath);
    }

    /**
     * @return BunnyCDNAdapter
     */
    public static function createPrefixFilesystemAdapter(): BunnyCDNAdapter
    {
        return static::createFilesystemAdapter(static::$prefixPath);
    }

    private static function bunnyCDNClient(): BunnyCDNClient
    {
        if (static::$bunnyCDNClient instanceof BunnyCDNClient) {
            return static::$bunnyCDNClient;
        }

        if (isset($_SERVER['STORAGEZONE'], $_SERVER['APIKEY'])) {
            static::$bunnyCDNClient = new BunnyCDNClient($_SERVER['STORAGEZONE'], $_SERVER['APIKEY']);

            return static::$bunnyCDNClient;
        }

        $filesystem = new Filesystem(new MemoryAdapter());

        $mock_client = Mockery::mock(
            new BunnyCDNClient(
                self::STORAGE_ZONE,
                'api-key',
                BunnyCDNRegion::FALKENSTEIN
            )
        );

        $mock_client->shouldReceive('list')->andReturnUsing(function($path) use ($filesystem) {
            return array_map(function(array $file) {
                return $file['type'] === 'dir'
                    ? MockClient::example_folder($file['path'], self::STORAGE_ZONE, [])
                    : MockClient::example_file($file['path'], self::STORAGE_ZONE, [
                        'Length' => $file['size']
                    ]);
            }, $filesystem->listContents($path));
        });

        $mock_client->shouldReceive('download')->andReturnUsing(function($path) use ($filesystem) {
            return $filesystem->read($path);
        });

        $mock_client->shouldReceive('stream')->andReturnUsing(function($path) use ($filesystem) {
            return $filesystem->readStream($path);
        });

        $mock_client->shouldReceive('upload')->andReturnUsing(function($path, $contents) use ($filesystem) {
            try {
                $filesystem->write($path, $contents);
            } catch (FileExistsException $e) {
                // Overwrite doesn't error in Bunny world
            }
            return"{status: 200}";
        });

        $mock_client->shouldReceive('make_directory')->andReturnUsing(function($path) use ($filesystem) {
            return $filesystem->createDir($path);
        });

        $mock_client->shouldReceive('delete')->andReturnUsing(function($path) use ($filesystem) {
            try {
                $filesystem->deleteDir($path);
                $filesystem->delete($path);
            } catch (FileNotFoundException $e) {

            }
        });

        static::$bunnyCDNClient = $mock_client;

        return static::$bunnyCDNClient;
    }

    public function givenItHasFile($path, $contents = self::TEST_FILE_CONTENTS)
    {
        $this->adapter->write($path, $contents, new Config());
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_write()
    {
        self::assertTrue(
            $this->adapter->write('testing/test.txt', 'Testing.txt', new Config())
        );

        self::assertTrue(
            $this->adapter->write('testing/test.txt', self::TEST_FILE_CONTENTS, new Config())
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_has()
    {
        $this->givenItHasFile('/testing/test.txt');

        self::assertTrue($this->adapter->has('/testing/test.txt'));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_has_slash_prefix()
    {
        $this->givenItHasFile('/testing/test.txt');

        self::assertTrue($this->adapter->has('/testing/test.txt'));
        self::assertTrue($this->adapter->has('//testing/test.txt'));
        self::assertTrue($this->adapter->has('///testing/test.txt'));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_has_inverse()
    {
        $this->givenItHasFile('/testing/test.txt');

        self::assertFalse($this->adapter->has('/not_in_test_files.txt'));
        self::assertFalse($this->adapter->has('not_a_directory'));
        self::assertFalse($this->adapter->has('not_a_testing/test.txt'));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_read()
    {

        $this->givenItHasFile('/testing/test.txt');

        self::assertEquals(
            self::TEST_FILE_CONTENTS,
            $this->adapter->read('/testing/test.txt')['contents']
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_delete()
    {
        self::assertTrue($this->adapter->write('/testing/test.txt', self::TEST_FILE_CONTENTS, new Config()));
        self::assertTrue($this->adapter->delete('/testing/test.txt'));
        self::assertTrue($this->adapter->write('/testing/test.txt', self::TEST_FILE_CONTENTS, new Config()));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_delete_dir()
    {

        self::assertTrue($this->adapter->createDir('testing_for_deletion',  new Config()));
        self::assertTrue($this->adapter->deleteDir('testing_for_deletion'));
    }

    /**
     * @note This is broken for directories, please only use on files
     *
     * @test
     * @throws Exception
     */
    public function it_copy()
    {
        $this->givenItHasFile('/testing/test.txt');

        self::assertTrue(
            $this->adapter->copy('testing/test.txt', 'testing/test_copied.txt')
        );

        self::assertTrue(
            $this->adapter->delete('testing/test_copied.txt')
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_list_contents()
    {
        $this->givenItHasFile('/testing/test.txt');

        self::assertIsArray(
            $this->adapter->listContents('/')
        );
        self::assertIsArray(
            $this->adapter->listContents('/')[0]
        );

        self::assertCount(
            2, $this->adapter->listContents('/', true)
        );

        $this->givenItHasFile('/testing/test2.txt');

        self::assertCount(
            3, $this->adapter->listContents('/', true)
        );

        $this->assertHasMetadataKeys(
            $this->adapter->listContents('/')[0]
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_list_contents_empty()
    {
        self::assertIsArray(
            $this->adapter->listContents('/')
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_get_size()
    {
        $this->givenItHasFile('/testing/test.txt');

        self::assertIsNumeric(
            $this->adapter->getSize('testing/test.txt')['size']
        );
    }

    /**
     * @throws \BunnyCDN\Storage\Exception
     * @test
     * @throws Exception
     */
    public function it_rename()
    {
        $this->givenItHasFile('/testing/test.txt');

        self::assertTrue(
            $this->adapter->rename('testing/test.txt', 'testing/test_renamed.txt')
        );

        self::assertTrue(
            $this->adapter->rename('testing/test_renamed.txt', 'testing/test.txt')
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_update()
    {
        $this->givenItHasFile('/testing/test.txt');

        self::assertTrue(
            $this->adapter->update('testing/test.txt', self::TEST_FILE_CONTENTS, new Config())
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_create_dir()
    {
        self::assertTrue(
            $this->adapter->createDir('testing_created/', new Config())
        );

        self::assertTrue(
            $this->adapter->deleteDir('testing_created/')
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_get_timestamp()
    {
        $this->givenItHasFile('/testing/test.txt');

        self::assertIsNumeric(
            $this->adapter->getTimestamp('testing/test.txt')['timestamp']
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_retrieve_metadata()
    {
        $this->givenItHasFile('/testing/test.txt');

        $metadata = $this->adapter->getMetadata('testing/test.txt');

        $this->assertHasMetadataKeys($metadata);
    }

    private function assertHasMetadataKeys($metadata) {
        self::assertArrayHasKey('type', $metadata);
        self::assertArrayHasKey('dirname', $metadata);
        self::assertArrayHasKey('mimetype', $metadata);
        self::assertArrayHasKey('guid', $metadata);
        self::assertArrayHasKey('path', $metadata);
        self::assertArrayHasKey('object_name', $metadata);
        self::assertArrayHasKey('size', $metadata);
        self::assertArrayHasKey('timestamp', $metadata);
        self::assertArrayHasKey('server_id', $metadata);
        self::assertArrayHasKey('user_id', $metadata);
        self::assertArrayHasKey('last_changed', $metadata);
        self::assertArrayHasKey('date_created', $metadata);
        self::assertArrayHasKey('storage_zone_name', $metadata);
        self::assertArrayHasKey('storage_zone_id', $metadata);
        self::assertArrayHasKey('checksum', $metadata);
        self::assertArrayHasKey('replicated_zones', $metadata);
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_tests_flysystem_compatibility()
    {
        $filesystem = new Filesystem($this->adapter);
        $this->givenItHasFile('/testing/test.txt');

        self::assertTrue($filesystem->createDir("test"));
        self::assertTrue($filesystem->deleteDir("test"));
    }

    /**
     * @test
     * @return void
     */
    public function it_get_public_url()
    {
        $this->givenItHasFile('/testing/test.txt');

        $this->assertEquals(self::PULLZONE_URL . 'testing/test.txt', $this->adapter->getUrl('/testing/test.txt'));

        $this->adapter->getUrl('/testing/test.txt');
    }

    /**
     * @test
     * @return void
     */
    public function it_cant_get_public_url()
    {
        $adapter = new BunnyCDNAdapter(
            new BunnyCDNClient(self::STORAGE_ZONE, 'api-key')
        );

        $this->givenItHasFile('/testing/test.txt');

        $this->expectException(RuntimeException::class);

        $this->assertEquals(self::PULLZONE_URL . 'testing/test.txt', $adapter->getUrl('/testing/test.txt'));
    }

    /**
     * Github Issue - 21
     * https://github.com/PlatformCommunity/flysystem-bunnycdn/issues/21
     *
     * Issue present where the date format can come back in either one of the following formats:
     * -    2022-04-10T17:43:49.297
     * -    2022-04-10T17:43:49
     *
     * Pretty sure I'm just going to create a static method called "parse_bunny_date" within the client to handle this.
     * @throws BunnyCDNException
     */
    public function test_regression_issue_21()
    {

        $client = new MockClient(self::STORAGE_ZONE, 'api-key');

        $client->add_response(
            new Response(200, [], json_encode(
                [
                    /**
                     * First with the milliseconds
                     */
                    array_merge(
                        $client::example_file('/subfolder/example_image.png', self::STORAGE_ZONE),
                        [
                            'LastChanged' => date('Y-m-d\TH:i:s.v'),
                            'DateCreated' => date('Y-m-d\TH:i:s.v'),
                        ]
                    ),
                    /**
                     * Then without
                     */
                    array_merge(
                        $client::example_file('/subfolder/example_image.png', self::STORAGE_ZONE),
                        [
                            'LastChanged' => date('Y-m-d\TH:i:s'),
                            'DateCreated' => date('Y-m-d\TH:i:s'),
                        ]
                    )
                ]
            ))
        );

        $adapter = new BunnyCDNAdapter($client);
        $response = $adapter->listContents('/');

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
    }

}
