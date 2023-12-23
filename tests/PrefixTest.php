<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\PathPrefixing\PathPrefixedAdapter;
use League\Flysystem\Visibility;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;

if (\is_file(__DIR__.'/ClientDI.php')) {
    require_once __DIR__.'/ClientDI.php';
}

class PrefixTest extends FilesystemAdapterTestCase
{
    /**
     * Path Prefix
     */
    public const PREFIX_PATH = 'path_prefix_12345';

    private static function bunnyCDNClient(): BunnyCDNClient
    {
        global $storage_zone;
        global $api_key;

        if ($storage_zone !== null && $api_key !== null) {
            return new BunnyCDNClient($storage_zone, $api_key);
        }

        return new MockClient('test_storage_zone', '123');
    }

    private static function bunnyCDNAdapter(): BunnyCDNAdapter
    {
        return new BunnyCDNAdapter(self::bunnyCDNClient(), 'https://example.org.local/assets/');
    }

    public static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new PathPrefixedAdapter(
            self::bunnyCDNAdapter(),
            self::PREFIX_PATH
        );
    }

    public function tearDown(): void
    {
        try {
            (new Filesystem(self::bunnyCDNAdapter()))->deleteDirectory('/'.self::PREFIX_PATH);
        } catch (FilesystemException $e) {
        }
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
     * Overwritten (usually because of visibility)
     */

    /**
     * We overwrite the test, because the original tries accessing the url
     *
     * @test
     */
    public function generating_a_public_url(): void
    {
        $url = $this->adapter()->publicUrl('path.txt', new Config());

        self::assertEquals('https://example.org.local/assets/path_prefix_12345/path.txt', $url);
    }

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
    public function get_checksum(): void
    {
        $adapter = $this->adapter();

        if (! $adapter instanceof ChecksumProvider) {
            $this->markTestSkipped('Adapter does not supply providing checksums');
        }

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

    public function test_construct_throws_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PrefixPath is no longer supported directly. Use PathPrefixedAdapter instead: https://flysystem.thephpleague.com/docs/adapter/path-prefixing/');
        new BunnyCDNAdapter(self::bunnyCDNClient(), 'https://example.org.local/assets/', 'thisisauselessarg');
    }

    /**
     * @test
     */
    public function prefix_path(): void
    {
        $this->runScenario(function () {
            $regularAdapter = self::bunnyCDNAdapter();
            $prefixPathAdapter = new PathPrefixedAdapter($regularAdapter, self::PREFIX_PATH);

            self::assertNotEmpty(
                self::PREFIX_PATH
            );

            self::assertIsString(
                self::PREFIX_PATH
            );

            $content = 'this is test';
            $prefixPathAdapter->write(
                'source.file.svg',
                $content,
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );

            self::assertTrue($prefixPathAdapter->fileExists(
                'source.file.svg'
            ));

            self::assertTrue($regularAdapter->directoryExists(
                self::PREFIX_PATH
            ));

            self::assertTrue($regularAdapter->fileExists(
                self::PREFIX_PATH.'/source.file.svg'
            ));

            $prefixPathAdapter->copy(
                'source.file.svg',
                'source.copy.file.svg',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );

            self::assertTrue($regularAdapter->fileExists(
                self::PREFIX_PATH.'/source.copy.file.svg'
            ));

            self::assertTrue($prefixPathAdapter->fileExists(
                'source.copy.file.svg'
            ));

            $prefixPathAdapter->delete(
                'source.copy.file.svg'
            );

            $this->assertEquals($content, $prefixPathAdapter->read('source.file.svg'));

            $this->assertEquals(
                $prefixPathAdapter->read('source.file.svg'),
                $regularAdapter->read(self::PREFIX_PATH.'/source.file.svg')
            );

            $this->assertEquals($content, stream_get_contents($prefixPathAdapter->readStream('source.file.svg')));

            $this->assertEquals(
                stream_get_contents($prefixPathAdapter->readStream('source.file.svg')),
                stream_get_contents($regularAdapter->readStream(self::PREFIX_PATH.'/source.file.svg'))
            );

            $this->assertSame(
                'image/svg+xml',
                $prefixPathAdapter->mimeType('source.file.svg')->mimeType()
            );

            $this->assertEquals(
                $prefixPathAdapter->mimeType('source.file.svg')->mimeType(),
                $regularAdapter->mimeType(self::PREFIX_PATH.'/source.file.svg')->mimeType()
            );

            $this->assertGreaterThan(
                0,
                $prefixPathAdapter->fileSize('source.file.svg')->fileSize()
            );

            $this->assertEquals(
                $prefixPathAdapter->fileSize('source.file.svg')->fileSize(),
                $regularAdapter->fileSize(self::PREFIX_PATH.'/source.file.svg')->fileSize()
            );

            $this->assertGreaterThan(
                time() - 30,
                $prefixPathAdapter->lastModified('source.file.svg')->lastModified()
            );

            $this->assertEquals(
                $prefixPathAdapter->lastModified('source.file.svg')->lastModified(),
                $regularAdapter->lastModified(self::PREFIX_PATH.'/source.file.svg')->lastModified()
            );

            $prefixPathAdapter->delete(
                'source.file.svg'
            );

            self::assertFalse($prefixPathAdapter->fileExists(
                'source.file.svg'
            ));

            $prefixPathAdapter->write(
                'subfolder/subfolder2/source.file.svg',
                $content,
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );

            self::assertTrue($regularAdapter->fileExists(
                self::PREFIX_PATH.'/subfolder/subfolder2/source.file.svg'
            ));

            $prefixPathAdapter->move(
                'subfolder',
                'newsubfolder',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );

            self::assertFalse($regularAdapter->fileExists(
                self::PREFIX_PATH.'/subfolder/subfolder2/source.file.svg'
            ));

            self::assertTrue($prefixPathAdapter->fileExists(
                'newsubfolder/subfolder2/source.file.svg'
            ));
        });
    }

    /**
     * @test
     *
     * @throws FilesystemException
     * @throws \Throwable
     */
    public function prefix_path_not_in_meta_pr_36(): void
    {
        $this->dontRetryOnException();

        $this->runScenario(function () {
            $prefixPathAdapter = $this->adapter();

            $prefixPathAdapter->write(
                'source.file.svg',
                '----',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC])
            );

            $contents = \iterator_to_array($prefixPathAdapter->listContents('/', false));

            $this->assertCount(1, $contents);
            $this->assertSame('source.file.svg', $contents[0]['path']);

            $prefixPathAdapter->delete('source.file.svg');
        });
    }
}
