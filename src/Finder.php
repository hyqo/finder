<?php

namespace Hyqo\Finder;

use FilesystemIterator;
use Generator;
use Hyqo\Finder\Exception\FinderException;
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

        $dir = new \RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS);
        yield from new \RecursiveIteratorIterator($dir);
    }

    /**
     * @param string $folder
     * @param null|string $extension
     * @return Generator<array-key,void,void,string>
     */
    public function find(string $folder, ?string $extension = null): Generator
    {
        foreach ($this->iterate($folder) as $file) {
            if (null === $extension || !strcasecmp($file->getExtension(), $extension)) {
                yield $file->getPathname();
            }
        }
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

        $folder = dirname($link);

        $this->mkdir($folder);

        if (is_file($link)) {
            return true;
        }

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
        $this->flushFolder($folder);

        rmdir($folder);

        return true;
    }
}
