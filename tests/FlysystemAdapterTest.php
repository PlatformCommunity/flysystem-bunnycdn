<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use Faker\Factory;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNRegion;
use PlatformCommunity\Flysystem\BunnyCDN\Util;
use PlatformCommunity\Flysystem\BunnyCDN\WriteBatchFile;
use Throwable;

if (\is_file(__DIR__.'/ClientDI.php')) {
    require_once __DIR__.'/ClientDI.php';
}

class FlysystemAdapterTest extends FilesystemAdapterTestCase
{
    public const DEMOURL = 'https://example.org.local';

    protected static bool $isLive = false;

    protected static string $publicUrl = self::DEMOURL;

    public static function setUpBeforeClass(): void
    {
        global $public_url;
        if (isset($public_url)) {
            static::$publicUrl = $public_url;
        }

        static::$publicUrl = rtrim(static::$publicUrl, '/');
    }

    private static function bunnyCDNClient(): BunnyCDNClient
    {
        global $storage_zone;
        global $api_key;
        global $region;

        if ($storage_zone !== null && $api_key !== null) {
            static::$isLive = true;

            return new BunnyCDNClient($storage_zone, $api_key, $region ?? BunnyCDNRegion::DEFAULT);
        }

        $mockedClient = new MockClient('test_storage_zone', '123');

        $mockedClient->guzzleClient = new Guzzle([
            'handler' => function (Request $request) use ($mockedClient) {
                $path = $request->getUri()->getPath();
                $method = $request->getMethod();

                if ($method === 'PUT' && $path === 'destination.txt') {
                    $mockedClient->filesystem->write('destination.txt', 'text');

                    return new Response(200);
                }

                if ($method === 'PUT' && $path === 'destination2.txt') {
                    $mockedClient->filesystem->write('destination2.txt', 'text2');

                    return new Response(200);
                }

                if ($method === 'PUT' && \in_array($path, ['failing.txt', 'failing2.txt'])) {
                    throw new \RuntimeException('Failed to write file');
                }

                throw new \RuntimeException('Unexpected request: '.$method.' '.$path);
            },
        ]);

        return $mockedClient;
    }

    public static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new BunnyCDNAdapter(self::bunnyCDNClient(), static::$publicUrl);
    }

    /**
     * Skipped
     */
    public function setting_visibility(): void
    {
        $this->markTestSkipped('No visibility support is provided for BunnyCDN');
    }

    public function generating_a_temporary_url(): void
    {
        $this->markTestSkipped('No temporary URL support is provided for BunnyCDN');
    }

    /**
     * @test
     */
    public function file_exists_on_directory_is_false(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            $this->assertFalse($adapter->directoryExists('test'));
            $adapter->createDirectory('test', new Config());
            $this->assertTrue($adapter->directoryExists('test'));
            $this->assertFalse($adapter->fileExists('test'));
        });
    }

    /**
     * @test
     */
    public function directory_exists_on_file_is_false(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            $this->assertFalse($adapter->fileExists('test.txt'));
            $adapter->write('test.txt', 'aaa', new Config());
            $this->assertTrue($adapter->fileExists('test.txt'));
            $this->assertFalse($adapter->directoryExists('test.txt'));
        });
    }

    /**
     * @test
     */
    public function delete_on_directory_throws_exception(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            $adapter->write(
                'test/text.txt',
                'contents',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );

            $this->expectException(UnableToDeleteFile::class);
            $adapter->delete('test/');
        });
    }

    /**
     * @test
     */
    public function delete_with_empty_path_throws_exception(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            $adapter->write(
                'test/text.txt',
                'contents',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );

            $this->expectException(UnableToDeleteFile::class);
            $adapter->delete('');
        });
    }

    /**
     * @test
     */
    public function moving_a_folder(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'test/text.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );
            $adapter->write(
                'test/2/text.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );
            $adapter->move('test', 'destination', new Config());
            $this->assertFalse(
                $adapter->fileExists('test/text.txt'),
                'After moving a file should no longer exist in the original location.'
            );
            $this->assertFalse(
                $adapter->fileExists('test/2/text.txt'),
                'After moving a file should no longer exist in the original location.'
            );
            $this->assertTrue(
                $adapter->fileExists('destination/text.txt'),
                'After moving, a file should be present at the new location.'
            );
            $this->assertTrue(
                $adapter->fileExists('destination/2/text.txt'),
                'After moving, a file should be present at the new location.'
            );
            $this->assertEquals('contents to be copied', $adapter->read('destination/text.txt'));
            $this->assertEquals('contents to be copied', $adapter->read('destination/2/text.txt'));
        });
    }

    /**
     * @test
     */
    public function moving_a_not_existing_folder(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            $this->expectException(UnableToMoveFile::class);
            $adapter->move('not_existing_file', 'destination', new Config());
        });
    }

    /**
     * @test
     */
    public function copying_a_folder(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'test/text.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );
            $adapter->write(
                'test/2/text.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );
            $adapter->copy('test', 'destination', new Config());
            $this->assertTrue(
                $adapter->fileExists('test/text.txt'),
                'After copying a file should exist in the original location.'
            );
            $this->assertTrue(
                $adapter->fileExists('test/2/text.txt'),
                'After copying a file should exist in the original location.'
            );
            $this->assertTrue(
                $adapter->fileExists('destination/text.txt'),
                'After copying, a file should be present at the new location.'
            );
            $this->assertTrue(
                $adapter->fileExists('destination/2/text.txt'),
                'After copying, a file should be present at the new location.'
            );
            $this->assertEquals('contents to be copied', $adapter->read('destination/text.txt'));
            $this->assertEquals('contents to be copied', $adapter->read('destination/2/text.txt'));
        });
    }

    /**
     * @test
     */
    public function copying_a_not_existing_folder(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            $this->expectException(UnableToCopyFile::class);
            $adapter->copy('not_existing_file', 'destination', new Config());
        });
    }

    /**
     * We overwrite the test, because the original tries accessing the url
     *
     * @test
     */
    public function generating_a_public_url(): void
    {
        if (self::$isLive && ! \str_starts_with(static::$publicUrl, self::DEMOURL)) {
            parent::generating_a_public_url();

            return;
        }

        $url = $this->adapter()->publicUrl('/path.txt', new Config());

        self::assertEquals(static::$publicUrl.'/path.txt', $url);
    }

    public function test_without_pullzone_url_error_thrown_accessing_url(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('In order to get a visible URL for a BunnyCDN object, you must pass the "pullzone_url" parameter to the BunnyCDNAdapter.');
        $myAdapter = new BunnyCDNAdapter(static::bunnyCDNClient());
        $myAdapter->publicUrl('/path.txt', new Config());
    }

    /**
     * @test
     */
    public function overwriting_a_file(): void
    {
        $this->runScenario(function () {
            $this->givenWeHaveAnExistingFile('path.txt', 'contents', ['visibility' => Visibility::PUBLIC]);
            $adapter = $this->adapter();

            $adapter->write('path.txt', 'new contents', new Config(['visibility' => Visibility::PRIVATE]));

            $contents = $adapter->read('path.txt');
            $this->assertEquals('new contents', $contents);
            // $visibility = $adapter->visibility('path.txt')->visibility();
            // $this->assertEquals(Visibility::PRIVATE, $visibility); // Commented out of this test
        });
    }

    /**
     * @test
     */
    public function moving_a_file_to_same_destination(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );
            $adapter->move('source.txt', 'source.txt', new Config());
            $this->assertTrue(
                $adapter->fileExists('source.txt'),
                'After moving a file to the same location the file should exist.'
            );
        });
    }

    /**
     * @test
     */
    public function get_checksum(): void
    {
        $adapter = $this->adapter();

        $adapter->write('path.txt', 'foobar', new Config());

        $this->assertSame(
            '3858f62230ac3c915f300c664312c63f',
            $adapter->checksum('path.txt', new Config())
        );

        $this->assertSame(
            'c3ab8ff13720e8ad9047dd39466b3c8974e592c2fa383d4a3960714caef0c4f2',
            $adapter->checksum('path.txt', new Config(['checksum_algo' => 'sha256']))
        );
    }

    public function test_checksum_throws_error_with_non_existing_file_on_default_algo(): void
    {
        $adapter = $this->adapter();

        $this->expectException(UnableToProvideChecksum::class);
        $adapter->checksum('path.txt', new Config(['checksum_algo' => 'sha256']));
    }

    //test_checksum_throws_error_with_empty_checksum_from_client
    public function test_checksum_throws_error_with_empty_checksum_from_client(): void
    {
        $client = $this->createMock(BunnyCDNClient::class);
        $client->expects(self::exactly(1))->method('list')->willReturnCallback(
            function () {
                ['file' => $file, 'dir' => $dir] = Util::splitPathIntoDirectoryAndFile('file.txt');
                $dir = Util::normalizePath($dir);
                $faker = Factory::create();
                $storage_zone = $faker->word;

                return [[
                    'Guid' => $faker->uuid,
                    'StorageZoneName' => $storage_zone,
                    'Path' => Util::normalizePath('/'.$storage_zone.'/'.$dir.'/'),
                    'ObjectName' => $file,
                    'Length' => $faker->numberBetween(0, 10240),
                    'LastChanged' => date('Y-m-d\TH:i:s.v'),
                    'ServerId' => $faker->numberBetween(0, 10240),
                    'ArrayNumber' => 0,
                    'IsDirectory' => false,
                    'UserId' => 'bf91bc4e-0e60-411a-b475-4416926d20f7',
                    'ContentType' => '',
                    'DateCreated' => date('Y-m-d\TH:i:s.v'),
                    'StorageZoneId' => $faker->numberBetween(0, 102400),
                    'Checksum' => null,
                    'ReplicatedZones' => '',
                ]];
            }
        );

        $adapter = new BunnyCDNAdapter($client);
        $this->expectException(UnableToProvideChecksum::class);
        $this->expectExceptionMessage('Unable to get checksum for file.txt: Checksum not available.');
        $adapter->checksum('file.txt', new Config(['checksum_algo' => 'sha256']));
    }

    /**
     * @test
     */
    public function fetching_the_mime_type_of_an_svg_file_by_file_name(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.svg',
                '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>',
                new Config()
            );

            $this->assertSame(
                'image/svg+xml',
                $adapter->detectMimeType('source.svg')
            );
        });
    }

    /**
     * Fix issue where `fopen` complains when opening downloaded image file#20
     * https://github.com/PlatformCommunity/flysystem-bunnycdn/pull/20
     *
     * @return void
     *
     * @throws FilesystemException
     * @throws Throwable
     */
    public function test_regression_pr_20()
    {
        $image = base64_decode('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z/C/HgAGgwJ/lK3Q6wAAAABJRU5ErkJggg==');
        $this->givenWeHaveAnExistingFile('path.png', $image);

        $this->runScenario(function () use ($image) {
            $adapter = $this->adapter();

            $stream = $adapter->readStream('path.png');

            $this->assertIsResource($stream);
            $this->assertEquals($image, stream_get_contents($stream));
        });
    }

    /**
     * Github Issue - 28
     * https://github.com/PlatformCommunity/flysystem-bunnycdn/issues/28
     *
     * Issue present where a lot of TypeErrors will appear if you ask for lastModified on Directory (returns FileAttributes)
     *
     * @throws FilesystemException
     */
    public function test_regression_issue_29()
    {
        $client = self::bunnyCDNClient();
        $client->make_directory('/example_folder');

        $adapter = new Filesystem(new BunnyCDNAdapter($client));
        $exception_count = 0;

        try {
            $adapter->fileSize('/example_folder');
        } catch (\Exception $e) {
            $this->assertInstanceOf(UnableToRetrieveMetadata::class, $e);
            $exception_count++;
        }

        try {
            $adapter->mimeType('/example_folder');
        } catch (\Exception $e) {
            $this->assertInstanceOf(UnableToRetrieveMetadata::class, $e);
            $exception_count++;
        }

        try {
            $adapter->lastModified('/example_folder');
        } catch (\Exception $e) {
            $this->assertInstanceOf(UnableToRetrieveMetadata::class, $e);
            $exception_count++;
        }

        // The fact that PHPUnit makes me do this is 🤬
        $this->assertEquals(3, $exception_count);
    }

    /**
     * Github Issue - 39
     * https://github.com/PlatformCommunity/flysystem-bunnycdn/issues/39
     *
     * Can't request file containing json
     *
     * @throws FilesystemException
     */
    public function test_regression_issue_39()
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            $adapter->write('test.json', json_encode(['test' => 123]), new Config([]));

            $response = $adapter->read('/test.json');

            $this->assertIsString($response);
        });
    }

    public function test_write_batch(): void
    {
        $this->runScenario(function () {
            $firstTmpFile = \tmpfile();
            fwrite($firstTmpFile, 'text');
            $firstTmpPath = stream_get_meta_data($firstTmpFile)['uri'];

            $secondTmpFile = \tmpfile();
            fwrite($secondTmpFile, 'text2');
            $secondTmpPath = stream_get_meta_data($secondTmpFile)['uri'];

            $adapter = $this->adapter();

            $adapter->writeBatch(
                [
                    new WriteBatchFile($firstTmpPath, 'destination.txt'),
                    new WriteBatchFile($secondTmpPath, 'destination2.txt'),
                ],
                new Config()
            );

            \fclose($firstTmpFile);
            \fclose($secondTmpFile);

            $this->assertSame('text', $adapter->read('destination.txt'));
            $this->assertSame('text2', $adapter->read('destination2.txt'));
        });
    }

    public function test_failing_write_batch(): void
    {
        if (self::$isLive) {
            $this->markTestSkipped('This test is not applicable in live mode');
        }

        $this->runScenario(function () {
            $firstTmpFile = \tmpfile();
            fwrite($firstTmpFile, 'text');
            $firstTmpPath = stream_get_meta_data($firstTmpFile)['uri'];

            $secondTmpFile = \tmpfile();
            fwrite($secondTmpFile, 'text2');
            $secondTmpPath = stream_get_meta_data($firstTmpFile)['uri'];

            $adapter = $this->adapter();

            $this->expectException(UnableToWriteFile::class);
            $adapter->writeBatch(
                [
                    new WriteBatchFile($firstTmpPath, 'failing.txt'),
                    new WriteBatchFile($secondTmpPath, 'failing2.txt'),
                ],
                new Config()
            );

            \fclose($firstTmpFile);
            \fclose($secondTmpFile);
        });
    }
}
