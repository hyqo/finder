<?php

namespace Hyqo\Finder;

use FilesystemIterator;
use Generator;
use Hyqo\Finder\Exception\FinderException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Finder
{
    /**
     * @param string $folder
     * @return Generator<array-key,void,void,SplFileInfo>
     */
    protected function iterate(string $folder): Generator
    {
        if (!is_dir($folder)) {
            return;
        }

        $folder = $this->normalizePath($folder);

        $dir = new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS);
        yield from new RecursiveIteratorIterator($dir);
    }

    /**
     * @param string $folder
     * @param null|string $extension
     * @return Generator<array-key,void,int,string>
     */
    public function find(string $folder, ?string $extension = null): Generator
    {
        $filesNum = 0;

        foreach ($this->iterate($folder) as $file) {
            if (null === $extension || !strcasecmp($file->getExtension(), $extension)) {
                $filesNum++;
                yield $file->getPathname();
            }
        }

        return $filesNum;
    }

    protected function normalizePath(string $path): string
    {
        return realpath($path);
    }

    public function load(string $filename): ?string
    {
        if (!file_exists($filename) || !is_file($filename)) {
            return null;
        }

        return file_get_contents($filename);
    }

    public function save(string $filename, string $content): bool
    {
        $this->mkdir(dirname($filename));

        return false !== file_put_contents($filename, $content, LOCK_EX);
    }

    public function mkdir(string $folder, int $permissions = 0777): true
    {
        if (is_dir($folder)) {
            return true;
        }

        if (@!mkdir($folder, $permissions, true)) {
            throw new FinderException(sprintf('Directory "%s" was not created', $folder));
        }

        return true;
    }

    public function symlink(string $target, string $link): bool
    {
        if (!is_file($target)) {
            throw new FinderException(sprintf("This is not a file: %s", $target));
        }

        if (file_exists($link)) {
            return true;
        }

        $folder = dirname($link);

        $this->mkdir($folder);

        return symlink($target, $link);
    }

    public function isEmpty(string $folder): bool
    {
        return !glob("$folder/*");
    }

    public function flushFolder(string $folder): true
    {
        foreach (glob("$folder/*") as $file) {
            if (is_dir($file)) {
                $this->flushFolder($file);
                rmdir($file);
            } else {
                unlink($file);
            }
        }

        return true;
    }

    public function removeFolder(string $folder): true
    {
        if (!is_dir($folder)) {
            return true;
        }

        $this->flushFolder($folder);

        rmdir($folder);

        return true;
    }

    public function wipe(string $folder, string $relativePath): bool
    {
        $folder = realpath($folder);

        if (!$folder) {
            return false;
        }

        $absolutePath = rtrim($folder . $relativePath, DIRECTORY_SEPARATOR);

        $lastChunkPattern = sprintf('#%1$s[^%1$s]*$#', DIRECTORY_SEPARATOR);

        while ($this->doWipe($folder, $absolutePath)) {
            $absolutePath = preg_replace($lastChunkPattern, '', $absolutePath);
        }

        return true;
    }

    protected function doWipe(string $folder, string $absolutePath): bool
    {
        $realpath = realpath($absolutePath);

        if (!$realpath) {
            return true;
        }

        if ($realpath === $folder) {
            return false;
        }

        if (is_dir($absolutePath)) {
            if ($this->isEmpty($absolutePath)) {
                rmdir($absolutePath);
            } else {
                return false;
            }
        } else {
            unlink($absolutePath);
        }

        return true;
    }
}
