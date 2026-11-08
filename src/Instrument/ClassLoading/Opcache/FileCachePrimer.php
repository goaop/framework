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

namespace Go\Instrument\ClassLoading\Opcache;

use Closure;
use FilesystemIterator;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Instrument\FileSystem\Enumerator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Primes the opcache file cache (`opcache.file_cache`) with the files a production request loads
 *
 * A fresh PHP-FPM master or CLI process starts with an empty opcache and compiles every file on first use. Compiled
 * at image build time into the file cache, the scripts are loaded from disk instead: the first request of a new
 * container is served several times faster. The cache belongs to the PHP build and to the absolute paths of the files,
 * so it is primed in the final image, with the same PHP binary and the same paths that serve the requests.
 *
 * Compiled scripts land on disk only in a process started with `opcache.enable_cli=1`, `opcache.file_cache_only=1`
 * and `opcache.file_cache=<directory>`: these are system settings, {@see configurationProblem()} checks them and
 * {@see commandLine()} spells out the command to run.
 *
 * Layout of the file cache: `<opcache.file_cache>/<system id of the PHP build>/<resolved path of the script>.bin`;
 * on Windows the drive keeps its letter but loses the colon (`C:\app` becomes `C\app`). Files are compiled by their
 * resolved path, which is how requests open them. `opcache.file_update_protection` (2 s by default) would keep the
 * files the warm-up wrote moments ago out of the cache, so it is set to 0 while compiling.
 *
 * @internal Used by the cache:warmup:opcache command
 */
final class FileCachePrimer
{
    /**
     * Tells why compiled scripts would not land in the file cache of this process, null when they do
     *
     * @param string|null                      $fileCacheDir Directory the file cache is expected in, null for whatever is configured
     * @param array<string, string|false>|null $settings     Ini values to check instead of the ones of this process
     *                                                       (system settings can not be changed at runtime)
     */
    public static function configurationProblem(?string $fileCacheDir = null, ?array $settings = null): ?string
    {
        $setting = static fn(string $name): string|false => $settings === null ? ini_get($name) : ($settings[$name] ?? false);

        if (!self::isEnabled($setting('opcache.enable_cli'))) {
            return 'opcache.enable_cli is off';
        }
        $configuredDir = (string) $setting('opcache.file_cache');
        if ($configuredDir === '') {
            return 'opcache.file_cache is not set';
        }
        if (!is_dir($configuredDir)) {
            return "the opcache.file_cache directory {$configuredDir} does not exist";
        }
        if (!is_writable($configuredDir)) {
            return "the opcache.file_cache directory {$configuredDir} is not writable";
        }
        if ($fileCacheDir !== null && realpath($fileCacheDir) !== realpath($configuredDir)) {
            return "opcache.file_cache is {$configuredDir}, not {$fileCacheDir}";
        }
        if (!self::isEnabled($setting('opcache.file_cache_only'))) {
            return 'opcache.file_cache_only is off';
        }
        // A read-only file cache is only read at runtime, nothing is ever written to it
        if (self::isEnabled($setting('opcache.file_cache_read_only'))) {
            return 'opcache.file_cache_read_only is on';
        }

        return null;
    }

    /**
     * Returns the command line that runs the given command with the settings compiled scripts need to land on disk
     *
     * @param string|null  $fileCacheDir Directory of the file cache, a placeholder when unknown
     * @param list<string> $arguments    The script and its arguments, as the current process got them
     */
    public static function commandLine(?string $fileCacheDir, array $arguments): string
    {
        $fileCacheDir ??= '<directory>';
        $settings = [
            'opcache.enable_cli=1',
            'opcache.file_cache_only=1',
            'opcache.file_cache=' . $fileCacheDir,
            'opcache.file_cache_read_only=0',
        ];
        $options = implode(' ', array_map(static fn(string $setting): string => '-d ' . $setting, $settings));

        return sprintf('mkdir -p %s && %s %s %s', $fileCacheDir, PHP_BINARY, $options, implode(' ', $arguments));
    }

    /**
     * Returns the files of the cache directory: woven files, proxies, advisor caches and the metadata files
     *
     * @return list<string>
     */
    public static function cacheFiles(string $cacheDir): array
    {
        if (!is_dir($cacheDir)) {
            return [];
        }
        // The iterator joins with the native separator; a stream wrapper path (`vfs://`, `phar://`) takes `/` only
        $isStreamPath = str_contains($cacheDir, '://');
        $files        = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = $isStreamPath ? str_replace('\\', '/', $file->getPathname()) : $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Returns the files of the application a request may load besides the cache: Composer's class map and its
     * autoloaded files, and the sources below the include paths that the cache knows as untransformed (a woven source
     * is never loaded in production, its woven file is)
     *
     * @return list<string>
     */
    public static function applicationFiles(AspectKernel $kernel, AopComposerLoader $autoloader): array
    {
        $options          = $kernel->getOptions();
        $cachePathManager = $kernel->getContainer()->getService(CachePathManager::class);

        $files      = [...array_values($autoloader->getOriginalLoader()->getClassMap()), ...$autoloader->autoloadedFiles()];
        $enumerator = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths']);
        foreach ($enumerator->enumerate() as $source) {
            $path = $source->getRealPath();
            if ($path !== false && $cachePathManager->findIncludeFile($path) === $path) {
                $files[] = $path;
            }
        }
        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * Compiles the files into the file cache, every file once, going on after a file that fails
     *
     * PHP's own report of a compile error is silenced while compiling (a fatal compile error does not end the process
     * here, opcache_compile_file() returns false), the failed file is reported to the callback instead. A file this
     * process included already (Composer's autoloaded files, the framework, the loader) went into the file cache when
     * it was included, and compiling it again fails when it declares functions: such a file counts as compiled.
     *
     * PHP quirk: a fatal compile error inside opcache_compile_file() (not a ParseError, which is thrown) bails out of
     * the engine and leaves user stream filters (php://filter) broken for the rest of the process. Harmless in the
     * dedicated process of the command; never compile such a file in a process that still weaves or runs tests.
     *
     * @param list<string>                 $files
     * @param Closure(string, ?string): void $onFile Called with every file and the reason it failed, null when compiled
     *
     * @return int Number of compiled files
     */
    public function compile(array $files, Closure $onFile): int
    {
        $includedFiles = array_fill_keys(get_included_files(), true);
        // Files written moments ago (the cache just warmed up) are cached too; errors are reported by the callback
        $settings = [
            'opcache.file_update_protection' => ini_set('opcache.file_update_protection', '0'),
            'display_errors'                 => ini_set('display_errors', '0'),
            'log_errors'                     => ini_set('log_errors', '0'),
        ];
        $compiled = 0;
        try {
            // The file cache is keyed by the resolved path, as the requests open it; a file listed twice is compiled once
            $realPaths = [];
            foreach ($files as $file) {
                $realPath = realpath($file);
                $realPaths[is_string($realPath) ? $realPath : $file] = $file;
            }
            foreach ($realPaths as $realPath => $file) {
                $error = $this->compileFile($realPath);
                if ($error !== null && isset($includedFiles[$realPath])) {
                    $error = null;
                }
                if ($error === null) {
                    $compiled++;
                }
                $onFile($file, $error);
            }
        } finally {
            foreach ($settings as $setting => $value) {
                if ($value !== false) {
                    ini_set($setting, $value);
                }
            }
        }

        return $compiled;
    }

    /**
     * Compiles one file, returning the reason it could not be compiled
     */
    private function compileFile(string $file): ?string
    {
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        });
        error_clear_last();
        try {
            $isCompiled = opcache_compile_file($file);
        } catch (Throwable $exception) {
            // ParseError, CompileError: thrown, not fatal
            return $exception::class . ': ' . $exception->getMessage();
        } finally {
            restore_error_handler();
        }
        if ($isCompiled) {
            return null;
        }
        // A fatal compile error bypasses the handler and is recorded as the last error
        $lastError = error_get_last();

        return $warning ?? ($lastError !== null ? $lastError['message'] : 'opcache_compile_file() returned false');
    }

    /**
     * Interprets an ini value as PHP does for a boolean setting (`1`, `On`, `true`, `yes`)
     */
    private static function isEnabled(string|false $value): bool
    {
        return $value !== false && filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
