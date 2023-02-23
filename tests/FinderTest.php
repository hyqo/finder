<?php

namespace Hyqo\Finder\Test;

use Hyqo\Finder\Exception\FinderException;
use Hyqo\Finder\Finder;
use PHPUnit\Framework\TestCase;

class FinderTest extends TestCase
{
    protected static string $folderA;
    protected static string $folderB;
    protected static string $folderBC;
    protected static string $folderRO;
    protected static string $folderC;
    protected static string $folderD;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $root = __DIR__ . '/var';
        @mkdir($root, recursive: true);

        self::$folderRO = "$root/ro";

        self::$folderA = "$root/a";
        self::$folderB = "$root/b";
        self::$folderBC = "$root/b-copy";
        self::$folderC = "$root/c";
        self::$folderD = "$root/d";

        @mkdir(self::$folderRO, 000, true);

        self::fillFolder(self::$folderA);
    }

    protected static function fillFolder(string $folder): void
    {
        @mkdir($folder . '/sub/sub', recursive: true);

        file_put_contents($folder . '/a.php', '');
        file_put_contents($folder . '/b.php', '');
        file_put_contents($folder . '/sub/c.php', '');
        file_put_contents($folder . '/sub/sub/d.php', '');
        file_put_contents($folder . '/e.txt', 'hello');
        file_put_contents($folder . '/f', '');
    }

    public function test_find_php(): void
    {
        $finder = new Finder();

        $expected = [
            self::$folderA . '/sub/sub/d.php',
            self::$folderA . '/sub/c.php',
            self::$folderA . '/b.php',
            self::$folderA . '/a.php',
        ];

        $actual = iterator_to_array($finder->find(self::$folderA, 'PHP'));

        sort($expected);
        sort($actual);

        $this->assertEquals($expected, $actual);
    }

    public function test_find_txt(): void
    {
        $finder = new Finder();

        $expected = [
            self::$folderA . '/e.txt',
        ];

        $actual = iterator_to_array($finder->find(self::$folderA, 'Txt'));

        sort($expected);
        sort($actual);

        $this->assertEquals($expected, $actual);
    }

    public function test_find_foo(): void
    {
        $finder = new Finder();

        $files = iterator_to_array($finder->find(self::$folderA, 'foo'));

        $this->assertEquals([], $files);
    }

    public function test_folder_does_not_exist(): void
    {
        $finder = new Finder();

        $files = iterator_to_array($finder->find(self::$folderA . '/abc', 'php'));

        $this->assertEquals([], $files);
    }

    public function test_successful_load(): void
    {
        $finder = new Finder();

        $content = $finder->load(self::$folderA . '/e.txt');

        $this->assertEquals("hello", $content);
    }

    public function test_failed_load(): void
    {
        $finder = new Finder();

        $content = $finder->load(self::$folderA . '/abc.txt');

        $this->assertNull($content);
    }

    public function test_successful_mkdir(): void
    {
        $finder = new Finder();

        $folder = self::$folderA . '/foo';

        try {
            $result = $finder->mkdir($folder);

            $this->assertTrue($result);
        } finally {
            rmdir($folder);
        }
    }

    public function test_failed_mkdir(): void
    {
        $finder = new Finder();

        $deniedFolder = self::$folderRO;

        if (!is_dir($deniedFolder)) {
            mkdir($deniedFolder, 0000);
        }

        $this->expectException(FinderException::class);
        $finder->mkdir($deniedFolder . '/folder');
    }

    public function test_successful_symlink(): void
    {
        $finder = new Finder();

        $targets = $finder->find(self::$folderA, 'php');

        foreach ($targets as $target) {
            $link = self::$folderC . '/' . basename($target);

            $result = $finder->symlink($target, $link);

            $this->assertTrue($result);
            $this->assertTrue(is_link($link));
            $this->assertEquals($target, readlink($link));
        }
    }

    public function test_failed_symlink(): void
    {
        $finder = new Finder();

        $target = __DIR__ . '/Fixtures/originals/abc';
        $link = __DIR__ . '/Fixtures/links/abc';

        $this->expectException(FinderException::class);
        $finder->symlink($target, $link);
    }

    public function test_flush_folder(): void
    {
        $finder = new Finder();

        self::fillFolder(self::$folderB);

        foreach ($finder->find(self::$folderB) as $target) {
            $finder->symlink($target, str_replace(self::$folderB, self::$folderBC, $target));
        }

        $this->assertFalse($finder->isEmpty(self::$folderBC));

        $finder->flushFolder(self::$folderBC);

        $this->assertTrue($finder->isEmpty(self::$folderBC));
        $this->assertDirectoryExists(self::$folderBC);
    }

    public function test_remove_folder(): void
    {
        $finder = new Finder();

        self::fillFolder(self::$folderB);

        foreach ($finder->find(self::$folderB) as $target) {
            $finder->symlink($target, str_replace(self::$folderB, self::$folderBC, $target));
        }

        $this->assertFalse($finder->isEmpty(self::$folderBC));

        $finder->removeFolder(self::$folderBC);

        $this->assertDirectoryDoesNotExist(self::$folderBC);
    }
}
