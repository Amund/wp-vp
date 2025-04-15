<?php

require_once __DIR__ . '/../class/VP_Cache.php';

use PHPUnit\Framework\TestCase;

// VP_Cache use VP_CACHE_PATH constant as storage basepath
// it must be defined before VP_Cache usage
define('VP_CACHE_PATH', sys_get_temp_dir() . '/vp-cache'); // => /tmp/vp-cache

final class VP_Cache_Test extends TestCase
{
    public function setUp(): void
    {
        if (is_dir(VP_CACHE_PATH)) {
            exec(sprintf('rm -r %s', VP_CACHE_PATH));
        }
    }

    public function tearDown(): void
    {
        if (is_dir(VP_CACHE_PATH)) {
            exec(sprintf('rm -r %s', VP_CACHE_PATH));
        }
    }

    public function testPath()
    {
        $path = VP_Cache::path();
        $this->assertEquals($path, VP_CACHE_PATH);

        $path = VP_Cache::path('foo');
        $this->assertEquals($path, VP_CACHE_PATH . '/foo');

        $path = VP_Cache::path('/foo');
        $this->assertEquals($path, VP_CACHE_PATH . '/foo');

        $path = VP_Cache::path('/foo/');
        $this->assertEquals($path, VP_CACHE_PATH . '/foo');

        $path = VP_Cache::path('foo-bar');
        $this->assertEquals($path, VP_CACHE_PATH . '/foo-bar');

        $path = VP_Cache::path('foo/bar');
        $this->assertEquals($path, VP_CACHE_PATH . '/foo-bar');

        $path = VP_Cache::path('foo/bar/');
        $this->assertEquals($path, VP_CACHE_PATH . '/foo-bar');
    }

    public function testGet()
    {
        mkdir(VP_CACHE_PATH, 0777, true);
        file_put_contents(VP_CACHE_PATH . '/key', 'value');

        $this->assertEquals('value', VP_Cache::get('key'));
    }

    public function testGetWithType()
    {
        mkdir(VP_CACHE_PATH . '/type', 0777, true);
        file_put_contents(VP_CACHE_PATH . '/type/key', 'value');

        $this->assertEquals('value', VP_Cache::get('key', 'type'));
    }

    public function testSet()
    {
        VP_Cache::set('key', 'value');

        $this->assertFileExists(VP_CACHE_PATH . '/key');
        $this->assertEquals('value', file_get_contents(VP_CACHE_PATH . '/key'));
    }

    public function testSetWithType()
    {
        VP_Cache::set('key', 'value', 'type');

        $this->assertDirectoryExists(VP_CACHE_PATH . '/type');
        $this->assertFileExists(VP_CACHE_PATH . '/type/key');
        $this->assertEquals('value', file_get_contents(VP_CACHE_PATH . '/type/key'));
    }

    public function testUnset()
    {
        mkdir(VP_CACHE_PATH, 0777, true);
        file_put_contents(VP_CACHE_PATH . '/key', 'value');

        $this->assertFileExists(VP_CACHE_PATH . '/key');

        VP_Cache::unset('key');

        $this->assertFileDoesNotExist(VP_CACHE_PATH . '/key');
    }

    public function testUnsetWithType()
    {
        mkdir(VP_CACHE_PATH . '/type', 0777, true);
        file_put_contents(VP_CACHE_PATH . '/type/key', 'value');

        $this->assertDirectoryExists(VP_CACHE_PATH . '/type');
        $this->assertFileExists(VP_CACHE_PATH . '/type/key');

        VP_Cache::unset('key', 'type');

        $this->assertDirectoryExists(VP_CACHE_PATH . '/type');
        $this->assertFileDoesNotExist(VP_CACHE_PATH . '/type/key');
    }

    public function testStat()
    {
        mkdir(VP_CACHE_PATH, 0777, true);
        file_put_contents(VP_CACHE_PATH . '/key', 'value');
        mkdir(VP_CACHE_PATH . '/foo', 0777, true);
        file_put_contents(VP_CACHE_PATH . '/foo/key', 'value');
        mkdir(VP_CACHE_PATH . '/bar', 0777, true);
        file_put_contents(VP_CACHE_PATH . '/bar/key', 'value');

        $this->assertFileExists(VP_CACHE_PATH . '/key');
        $this->assertDirectoryExists(VP_CACHE_PATH . '/foo');
        $this->assertDirectoryExists(VP_CACHE_PATH . '/bar');
        $this->assertFileExists(VP_CACHE_PATH . '/foo/key');
        $this->assertFileExists(VP_CACHE_PATH . '/bar/key');

        $stat = VP_Cache::stat();
        $this->assertEquals($stat, [
            'type' => [
                'bar' => 1,
                'foo' => 1,
            ],
            'typed' => 2,
            'root' => 1,
            'total' => 3,
        ]);
    }

    public function testClear()
    {
        mkdir(VP_CACHE_PATH, 0777, true);
        file_put_contents(VP_CACHE_PATH . '/key', 'value');
        mkdir(VP_CACHE_PATH . '/foo', 0777, true);
        file_put_contents(VP_CACHE_PATH . '/foo/key', 'value');
        mkdir(VP_CACHE_PATH . '/bar', 0777, true);
        file_put_contents(VP_CACHE_PATH . '/bar/key', 'value');

        $this->assertFileExists(VP_CACHE_PATH . '/key');
        $this->assertDirectoryExists(VP_CACHE_PATH . '/foo');
        $this->assertDirectoryExists(VP_CACHE_PATH . '/bar');
        $this->assertFileExists(VP_CACHE_PATH . '/foo/key');
        $this->assertFileExists(VP_CACHE_PATH . '/bar/key');

        VP_Cache::clear();

        $this->assertFileExists(VP_CACHE_PATH . '/key');
        $this->assertDirectoryDoesNotExist(VP_CACHE_PATH . '/foo');
        $this->assertDirectoryDoesNotExist(VP_CACHE_PATH . '/bar');
    }

    public function testClearWithType()
    {
        mkdir(VP_CACHE_PATH, 0777, true);
        file_put_contents(VP_CACHE_PATH . '/key', 'value');
        mkdir(VP_CACHE_PATH . '/foo', 0777, true);
        file_put_contents(VP_CACHE_PATH . '/foo/key', 'value');
        mkdir(VP_CACHE_PATH . '/bar', 0777, true);
        file_put_contents(VP_CACHE_PATH . '/bar/key', 'value');

        $this->assertFileExists(VP_CACHE_PATH . '/key');
        $this->assertDirectoryExists(VP_CACHE_PATH . '/foo');
        $this->assertDirectoryExists(VP_CACHE_PATH . '/bar');
        $this->assertFileExists(VP_CACHE_PATH . '/foo/key');
        $this->assertFileExists(VP_CACHE_PATH . '/bar/key');

        VP_Cache::clear('foo');

        $this->assertFileExists(VP_CACHE_PATH . '/key');
        $this->assertDirectoryDoesNotExist(VP_CACHE_PATH . '/foo');
        $this->assertDirectoryExists(VP_CACHE_PATH . '/bar');
    }

    public function testFlush()
    {
        mkdir(VP_CACHE_PATH, 0777, true);
        file_put_contents(VP_CACHE_PATH . '/key', 'value');
        mkdir(VP_CACHE_PATH . '/foo', 0777, true);
        file_put_contents(VP_CACHE_PATH . '/foo/key', 'value');
        mkdir(VP_CACHE_PATH . '/bar', 0777, true);
        file_put_contents(VP_CACHE_PATH . '/bar/key', 'value');

        $this->assertFileExists(VP_CACHE_PATH . '/key');
        $this->assertDirectoryExists(VP_CACHE_PATH . '/foo');
        $this->assertDirectoryExists(VP_CACHE_PATH . '/bar');
        $this->assertFileExists(VP_CACHE_PATH . '/foo/key');
        $this->assertFileExists(VP_CACHE_PATH . '/bar/key');

        VP_Cache::flush();

        $this->assertFileDoesNotExist(VP_CACHE_PATH . '/key');
        $this->assertDirectoryDoesNotExist(VP_CACHE_PATH . '/foo');
        $this->assertDirectoryDoesNotExist(VP_CACHE_PATH . '/bar');
    }
}
