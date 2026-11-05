<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

/**
 * Stream wrapper that only answers stat calls of readable files with a fixed mtime and counts them per path
 */
final class CountingStatWrapper
{
    /**
     * @var array<string, int> Number of stat calls per path
     */
    public static array $statCalls = [];

    /**
     * @var array<string, int> Modification time per path, a path that is not listed does not exist
     */
    public static array $mtimes = [];

    /** @var resource|null Stream context, set by PHP */
    public $context;

    /**
     * @param array<string, int> $mtimes
     */
    public static function reset(array $mtimes): void
    {
        self::$statCalls = [];
        self::$mtimes    = $mtimes;
    }

    /**
     * @return array<string|int, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        self::$statCalls[$path] = (self::$statCalls[$path] ?? 0) + 1;
        if (!isset(self::$mtimes[$path])) {
            return false;
        }
        $stat = array_fill_keys(
            ['dev', 'ino', 'mode', 'nlink', 'uid', 'gid', 'rdev', 'size', 'atime', 'mtime', 'ctime', 'blksize', 'blocks'],
            0,
        );
        $stat['mode']  = 0100644;
        $stat['mtime'] = self::$mtimes[$path];

        return $stat;
    }
}
