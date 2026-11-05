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

namespace Go\PhpUnit;

use Go\VirtualFileSystem\FileSystem;
use Go\VirtualFileSystem\StreamWrapper;

/**
 * Stream wrapper of a mounted virtual file system that records every path PHP asks the status of
 *
 * The status calls of a stream wrapper (`url_stat()`: file_exists(), is_dir(), is_writable(), filemtime(), stat(),
 * ...) are the ones a request pays for with the native file system. PHP's stat cache is not involved: a wrapper is
 * asked every time.
 */
final class StatCountingStreamWrapper
{
    /**
     * Paths asked for, in order
     *
     * @var list<string>
     */
    public static array $statPaths = [];

    /** @var resource|null */
    public $context;

    private readonly StreamWrapper $wrapper;

    public function __construct()
    {
        $this->wrapper = new StreamWrapper();
    }

    /**
     * Puts the wrapper in front of a mounted virtual file system
     */
    public static function install(FileSystem $fileSystem, string $scheme): void
    {
        assert(FileSystem::get($scheme) === $fileSystem);
        stream_wrapper_unregister($scheme);
        stream_wrapper_register($scheme, self::class);
        self::$statPaths = [];
    }

    /**
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        self::$statPaths[] = $path;

        return $this->wrapper->url_stat($path, $flags);
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return $this->wrapper->stream_open($path, $mode, $options, $openedPath);
    }

    public function stream_read(int $count): string
    {
        return $this->wrapper->stream_read($count);
    }

    public function stream_write(string $data): int
    {
        return $this->wrapper->stream_write($data);
    }

    public function stream_tell(): int
    {
        return $this->wrapper->stream_tell();
    }

    public function stream_eof(): bool
    {
        return $this->wrapper->stream_eof();
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        return $this->wrapper->stream_seek($offset, $whence);
    }

    public function stream_truncate(int $newSize): bool
    {
        return $this->wrapper->stream_truncate($newSize);
    }

    public function stream_flush(): bool
    {
        return $this->wrapper->stream_flush();
    }

    public function stream_close(): void
    {
        $this->wrapper->stream_close();
    }

    /**
     * @return array<int|string, int>|false
     */
    public function stream_stat(): array|false
    {
        return $this->wrapper->stream_stat();
    }

    public function stream_lock(int $operation): bool
    {
        return $this->wrapper->stream_lock($operation);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return $this->wrapper->stream_set_option($option, $arg1, $arg2);
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return $this->wrapper->stream_metadata($path, $option, $value);
    }

    public function unlink(string $path): bool
    {
        return $this->wrapper->unlink($path);
    }

    public function rename(string $pathFrom, string $pathTo): bool
    {
        return $this->wrapper->rename($pathFrom, $pathTo);
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return $this->wrapper->mkdir($path, $mode, $options);
    }

    public function rmdir(string $path, int $options): bool
    {
        return $this->wrapper->rmdir($path, $options);
    }

    public function dir_opendir(string $path, int $options): bool
    {
        return $this->wrapper->dir_opendir($path, $options);
    }

    public function dir_readdir(): string|false
    {
        return $this->wrapper->dir_readdir();
    }

    public function dir_rewinddir(): bool
    {
        return $this->wrapper->dir_rewinddir();
    }

    public function dir_closedir(): bool
    {
        return $this->wrapper->dir_closedir();
    }
}
