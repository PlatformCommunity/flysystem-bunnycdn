<?php

namespace PlatformCommunity\Flysystem\BunnyCDN\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use PlatformCommunity\Flysystem\BunnyCDN\Util;

class UtilityClassTest extends TestCase
{
    /**
     * @test
     *
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
     *
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
     *
     * @throws Exception
     */
    public function it_tests_normalize_path()
    {
        $this->assertEquals(
            'test/',
            Util::normalizePath('/test/', true)
        );

        $this->assertEquals(
            'test/',
            Util::normalizePath('/test', true)
        );

        $this->assertEquals(
            'test',
            Util::normalizePath('/test', false)
        );
    }

    /**
     * @test
     *
     * @throws Exception
     */
    public function it_path_split()
    {
        $this->assertEquals(
            [
                'file' => 'testing-dir',
                'dir' => '',
            ],
            Util::splitPathIntoDirectoryAndFile('/testing-dir')
        );

        $this->assertEquals(
            [
                'file' => 'testing.txt',
                'dir' => '',
            ],
            Util::splitPathIntoDirectoryAndFile('/testing.txt')
        );

        $this->assertEquals(
            [
                'file' => 'testing-dir',
                'dir' => '',
            ],
            Util::splitPathIntoDirectoryAndFile('/testing-dir/')
        );

        $this->assertEquals(
            [
                'file' => 'file.txt',
                'dir' => '/testing-dir',
            ],
            Util::splitPathIntoDirectoryAndFile('/testing-dir/file.txt')
        );

        $this->assertEquals(
            [
                'file' => 'file.txt',
                'dir' => '/testing-dir/nested',
            ],
            Util::splitPathIntoDirectoryAndFile('/testing-dir/nested/file.txt')
        );
    }

    public function test_replace_first()
    {
        $this->assertSame(
            'SX',
            Util::replaceFirst('X', 'S', 'XX')
        );

        $this->assertSame(
            'ORIGINAL',
            Util::replaceFirst('X', 'S', 'ORIGINAL')
        );
    }
}
