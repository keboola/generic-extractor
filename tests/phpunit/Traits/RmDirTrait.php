<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Traits;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

trait RmDirTrait
{
    protected function rmDir(string $dirPath): void
    {
        if (!file_exists($dirPath)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $dirPath,
                FilesystemIterator::SKIP_DOTS,
            ),
            RecursiveIteratorIterator::CHILD_FIRST,
        ) as $path) {
            $path->isDir() && !$path->isLink() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        }
        rmdir($dirPath);
    }
}
