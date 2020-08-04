<?php


use PlatformCommunity\Flysystem\BunnyCDN\Util;
use PHPUnit\Framework\TestCase;

class UtilityClassTest extends TestCase
{
    /**
     * @test
     * @throws Exception
     */
    public function it_starts_with()
    {
        $this->assertTrue(
            Util::startsWith('/test', '/')
        );

        $this->assertFalse(
            Util::startsWith('test', '/')
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_ends_with()
    {
        $this->assertTrue(
            Util::endsWith('test/', '/')
        );

        $this->assertFalse(
            Util::endsWith('test', '/')
        );

        $this->assertTrue(
            Util::endsWith('test', '')
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_tests_normalize_path()
    {
        $this->assertEquals(
            Util::normalizePath('/test/', true),
            'test/'
        );

        $this->assertEquals(
            Util::normalizePath('/test', true),
            'test/'
        );

        $this->assertEquals(
            Util::normalizePath('/test', false),
            'test'
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_path_split()
    {
        $this->assertEquals(
            Util::splitPathIntoDirectoryAndFile('/testing-dir'),
            [
                'file' => 'testing-dir',
                'dir' => '',
            ]
        );

        $this->assertEquals(
            Util::splitPathIntoDirectoryAndFile('/testing-dir/'),
            [
                'file' => 'testing-dir',
                'dir' => '',
            ]
        );

        $this->assertEquals(
            Util::splitPathIntoDirectoryAndFile('/testing-dir/file.txt'),
            [
                'file' => 'file.txt',
                'dir' => '/testing-dir',
            ]
        );

        $this->assertEquals(
            Util::splitPathIntoDirectoryAndFile('/testing-dir/nested/file.txt'),
            [
                'file' => 'file.txt',
                'dir' => '/testing-dir/nested',
            ]
        );
    }
}
