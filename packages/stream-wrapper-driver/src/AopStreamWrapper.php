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

namespace Go\Driver\StreamWrapper;

use Closure;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Driver\StreamWrapper\Exception\StreamWrapperRegistrationException;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\FileSystem\Enumerator;
use Go\Instrument\PathResolver;

use function is_array;
use function is_int;
use function is_resource;
use function is_string;
use function strlen;

/**
 * The `file://` stream wrapper of the stream wrapper driver: every file of the process is served by delegating
 * to the native wrapper, a file included from within the weaving scope is served woven
 *
 * PHP has one `file://` wrapper per process, so this class takes the place of the native one
 * (stream_wrapper_unregister('file') + stream_wrapper_register('file', ...)). Every protocol method restores
 * the native wrapper for the duration of one native call and registers this class again afterwards
 * ({@see self::native()}); the opened handle is a native stream, so reads and writes never re-enter the wrapper.
 *
 * An include (`$options & STREAM_OPEN_FOR_INCLUDE`, see {@see self::STREAM_OPEN_FOR_INCLUDE}) of a file within
 * the weaving scope reads the file and hands it to {@see SourceTransformingLoader::transformSource()}, the shared
 * cache-or-transform pipeline of the source-transforming drivers; a result differing from the source is served
 * from a `php://memory` stream under the original path. opcache caches such a file by its original path like any
 * other (a `php://filter` include never is cached), as long as {@see self::stream_stat()} reports the mtime of the
 * original file and the length of the served content as size: PHP reads exactly `st_size` bytes of an included
 * stream.
 *
 * @internal Registered by StreamWrapperDriver, instantiated by PHP
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 */
final class AopStreamWrapper
{
    /**
     * `STREAM_OPEN_FOR_INCLUDE` of main/php_streams.h: set on the options of stream_open() for include/require,
     * not exposed to userland as a constant
     */
    private const int STREAM_OPEN_FOR_INCLUDE = 0x80;

    private const string PROTOCOL = 'file';

    /**
     * Stream context, set by PHP on the instance when the caller passed one
     */
    public mixed $context = null;

    /**
     * Container the transformation pipeline is wired to
     */
    private static ?AspectContainer $container = null;

    /**
     * Decides whether a resolved path lies within the weaving scope (appDir, includePaths, excludePaths)
     *
     * @var (Closure(string): bool)|null
     */
    private static ?Closure $isWithinWeavingScope = null;

    /**
     * Whether this class is the registered `file://` wrapper
     */
    private static bool $registered = false;

    /**
     * Nesting depth of {@see self::native()}: the native wrapper is restored by the outermost call only
     */
    private static int $nativeDepth = 0;

    /**
     * Native stream of the opened file, or the `php://memory` stream serving a woven include
     *
     * @var resource|null
     */
    private $stream;

    /**
     * Native directory handle of dir_opendir()
     *
     * @var resource|null
     */
    private $directory;

    /**
     * Original path of an include served from memory: its stat is reported with the served length
     */
    private ?string $servedPath = null;

    /**
     * Length of the content served from memory
     */
    private int $servedLength = 0;

    /**
     * Takes the place of the native `file://` wrapper for the given container and weaving scope
     *
     * Idempotent: a second call reconfigures the scope and keeps the registration.
     *
     * @phpstan-param KernelOptions $options Normalized kernel options
     *
     * @throws StreamWrapperRegistrationException When PHP refuses the registration (the native wrapper is restored)
     */
    public static function register(AspectContainer $container, array $options): void
    {
        self::$container            = $container;
        self::$isWithinWeavingScope = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths'])
            ->getPathFilter();

        if (self::$registered) {
            return;
        }
        // Loaded ahead: an include triggers stream_open(), which needs both classes before it can tell whether the
        // included file lies within the scope. Autoloading them from there would include their own files through
        // this wrapper while the class is already being autoloaded, which PHP reports as "class not found"
        class_exists(PathResolver::class);
        class_exists(SourceTransformingLoader::class);
        stream_wrapper_unregister(self::PROTOCOL);
        if (!stream_wrapper_register(self::PROTOCOL, self::class)) {
            // @codeCoverageIgnoreStart (PHP refuses only a protocol still taken or an unknown class)
            stream_wrapper_restore(self::PROTOCOL);

            throw StreamWrapperRegistrationException::fileWrapperNotReplaced(self::class);
            // @codeCoverageIgnoreEnd
        }
        self::$registered  = true;
        self::$nativeDepth = 0;
    }

    /**
     * Restores the native `file://` wrapper and forgets the container and the scope
     *
     * @internal For tests and processes that boot the framework again
     */
    public static function unregister(): void
    {
        if (self::$registered) {
            stream_wrapper_restore(self::PROTOCOL);
        }
        self::$registered           = false;
        self::$nativeDepth          = 0;
        self::$container            = null;
        self::$isWithinWeavingScope = null;
    }

    public static function isRegistered(): bool
    {
        return self::$registered;
    }

    /**
     * @param string|null $openedPath Set to the path actually opened when STREAM_USE_PATH searched the include path
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $usePath      = ($options & STREAM_USE_PATH) !== 0;
        $reportErrors = ($options & STREAM_REPORT_ERRORS) !== 0;
        // Opened natively first, so that a missing or unreadable file fails exactly like it does without the driver
        $stream = self::native(
            static fn(): mixed => $reportErrors ? fopen($path, $mode, $usePath) : @fopen($path, $mode, $usePath),
        );
        if (!is_resource($stream)) {
            return false;
        }
        $this->stream = $stream;
        if ($usePath) {
            // The include path was searched: the uri of the native stream is the path that was found
            $path       = stream_get_meta_data($stream)['uri'] ?? $path;
            $openedPath = $path;
        }

        if (($options & self::STREAM_OPEN_FOR_INCLUDE) !== 0) {
            $this->serveInclude($path);
        }

        return true;
    }

    public function stream_read(int $count): string|false
    {
        if ($this->stream === null) {
            return false;
        }

        return $count > 0 ? fread($this->stream, $count) : '';
    }

    public function stream_write(string $data): int|false
    {
        return $this->stream === null ? false : fwrite($this->stream, $data);
    }

    public function stream_eof(): bool
    {
        return $this->stream === null || feof($this->stream);
    }

    public function stream_close(): void
    {
        if ($this->stream !== null) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    public function stream_flush(): bool
    {
        return $this->stream !== null && fflush($this->stream);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        return $this->stream !== null && fseek($this->stream, $offset, $whence) === 0;
    }

    public function stream_tell(): int|false
    {
        return $this->stream === null ? false : ftell($this->stream);
    }

    /**
     * Stat of the opened file: for a woven include the native stat of the original path (its mtime keeps opcache
     * validating the file as usual) with the served length as size, as PHP reads exactly `st_size` bytes of an
     * included stream
     *
     * @return array<int|string, int>|false
     */
    public function stream_stat(): array|false
    {
        if ($this->servedPath !== null) {
            $servedPath = $this->servedPath;
            $stat       = self::native(static fn(): array|false => @stat($servedPath));
            if ($stat === false) {
                return false;
            }
            $stat[7]      = $this->servedLength;
            $stat['size'] = $this->servedLength;

            return $stat;
        }

        return $this->stream === null ? false : fstat($this->stream);
    }

    public function stream_truncate(int $newSize): bool
    {
        return $this->stream !== null && $newSize >= 0 && ftruncate($this->stream, $newSize);
    }

    public function stream_lock(int $operation): bool
    {
        if ($this->stream === null) {
            return false;
        }
        // flock() first asks whether the stream supports locking at all (operation 0); a memory-served include has
        // no file to lock, so every request is answered with success
        if ($operation === 0 || $this->servedPath !== null) {
            return true;
        }

        // PHP hands over LOCK_SH, LOCK_EX or LOCK_UN, with LOCK_NB possibly set: the values flock() accepts
        return flock($this->stream, $operation & (LOCK_SH | LOCK_EX | LOCK_UN | LOCK_NB));
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return false;
    }

    /**
     * @return resource|false The native handle for stream_select(), nothing for a memory-served include
     */
    public function stream_cast(int $castAs): mixed
    {
        if ($this->stream === null || $this->servedPath !== null) {
            return false;
        }

        return $this->stream;
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return self::native(static function () use ($path, $option, $value): bool {
            switch ($option) {
                case STREAM_META_TOUCH:
                    $times = is_array($value) ? $value : [];
                    $mtime = is_int($times[0] ?? null) ? $times[0] : null;
                    $atime = is_int($times[1] ?? null) ? $times[1] : null;

                    return touch($path, $mtime, $atime);
                case STREAM_META_OWNER:
                case STREAM_META_OWNER_NAME:
                    return (is_int($value) || is_string($value)) && chown($path, $value);
                case STREAM_META_GROUP:
                case STREAM_META_GROUP_NAME:
                    return (is_int($value) || is_string($value)) && chgrp($path, $value);
                case STREAM_META_ACCESS:
                    return is_int($value) && chmod($path, $value);
                default:
                    return false;
            }
        });
    }

    /**
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        $quiet = ($flags & STREAM_URL_STAT_QUIET) !== 0;
        $link  = ($flags & STREAM_URL_STAT_LINK) !== 0;

        return self::native(static function () use ($path, $quiet, $link): array|false {
            if ($link) {
                return $quiet ? @lstat($path) : lstat($path);
            }

            return $quiet ? @stat($path) : stat($path);
        });
    }

    public function unlink(string $path): bool
    {
        return self::native(static fn(): bool => unlink($path));
    }

    public function rename(string $from, string $to): bool
    {
        return self::native(static fn(): bool => rename($from, $to));
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        $recursive    = ($options & STREAM_MKDIR_RECURSIVE) !== 0;
        $reportErrors = ($options & STREAM_REPORT_ERRORS) !== 0;

        return self::native(
            static fn(): bool => $reportErrors ? mkdir($path, $mode, $recursive) : @mkdir($path, $mode, $recursive),
        );
    }

    public function rmdir(string $path, int $options): bool
    {
        $reportErrors = ($options & STREAM_REPORT_ERRORS) !== 0;

        return self::native(static fn(): bool => $reportErrors ? rmdir($path) : @rmdir($path));
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $directory = self::native(static fn(): mixed => @opendir($path));
        if (!is_resource($directory)) {
            return false;
        }
        $this->directory = $directory;

        return true;
    }

    public function dir_readdir(): string|false
    {
        return $this->directory === null ? false : readdir($this->directory);
    }

    public function dir_rewinddir(): bool
    {
        if ($this->directory === null) {
            return false;
        }
        rewinddir($this->directory);

        return true;
    }

    public function dir_closedir(): bool
    {
        if ($this->directory !== null) {
            closedir($this->directory);
            $this->directory = null;
        }

        return true;
    }

    /**
     * Serves an included file woven when it lies within the weaving scope
     *
     * Runs with this wrapper registered on purpose: the transformer chain includes other files meanwhile (parent
     * classes and interfaces of the woven class are autoloaded for reflection) and they must be woven too.
     */
    private function serveInclude(string $path): void
    {
        $container            = self::$container;
        $isWithinWeavingScope = self::$isWithinWeavingScope;
        $stream               = $this->stream;
        if ($container === null || $isWithinWeavingScope === null || $stream === null) {
            return;
        }
        $resolvedPath = PathResolver::realpath($path);
        $resolvedPath = is_string($resolvedPath) ? $resolvedPath : $path;
        if (!$isWithinWeavingScope($resolvedPath)) {
            return;
        }

        $source = stream_get_contents($stream);
        if ($source === false) {
            // @codeCoverageIgnoreStart (a readable native file handle never fails here)
            return;
            // @codeCoverageIgnoreEnd
        }
        SourceTransformingLoader::ensureRegistered($container);
        // The transformer metadata reads the uri of the given stream: the native handle of the file
        $served = SourceTransformingLoader::transformSource($resolvedPath, $source, $stream);
        if ($served === $source) {
            rewind($stream);

            return;
        }

        $memory = fopen('php://memory', 'r+b');
        if ($memory === false) {
            // @codeCoverageIgnoreStart (php://memory always opens)
            rewind($stream);

            return;
            // @codeCoverageIgnoreEnd
        }
        fwrite($memory, $served);
        rewind($memory);
        fclose($stream);
        $this->stream       = $memory;
        $this->servedPath   = $resolvedPath;
        $this->servedLength = strlen($served);
    }

    /**
     * Runs one operation against the native `file://` wrapper
     *
     * The outermost call restores the native wrapper and registers this class again afterwards; a nested call
     * (an operation that calls into this class again) runs with the wrapper already restored.
     *
     * @template T
     *
     * @param Closure(): T $operation
     *
     * @return T
     */
    private static function native(Closure $operation): mixed
    {
        if (self::$nativeDepth === 0 && self::$registered) {
            stream_wrapper_restore(self::PROTOCOL);
        }
        self::$nativeDepth++;
        try {
            return $operation();
        } finally {
            self::$nativeDepth--;
            if (self::$nativeDepth === 0 && self::$registered) {
                stream_wrapper_unregister(self::PROTOCOL);
                stream_wrapper_register(self::PROTOCOL, self::class);
            }
        }
    }
}
