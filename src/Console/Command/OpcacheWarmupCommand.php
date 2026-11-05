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

namespace Go\Console\Command;

use Closure;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\Instrument\ClassLoading\ComposerAutoloader;
use Go\Instrument\ClassLoading\Opcache\FileCachePrimer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command that primes the opcache file cache with the files a production request loads
 *
 * The file cache belongs to the PHP build and to the paths of the files, so the command runs in the final image, after
 * `cache:warmup:aop`, with the PHP binary that serves the requests. Compiled scripts land on disk only in a process
 * started with `opcache.enable_cli=1 opcache.file_cache_only=1 opcache.file_cache=<directory>`; these are system
 * settings, so the command checks them and prints the exact command to run instead of starting another process
 * (symfony/process is no dependency of the framework, and a re-executed process would inherit the environment,
 * the ini scan directory and the memory limit only by accident).
 *
 * The AOP cache, the loader file and the entry points must compile; a file of the Composer class map that does not
 * (a vendor stub written for a newer PHP) is reported and skipped, see FileCachePrimer.
 */
#[AsCommand(
    name: 'cache:warmup:opcache',
    description: 'Prime the opcache file cache with the AOP cache and the application',
    help: <<<EOT
Compiles into the opcache file cache (<info>opcache.file_cache</info>) what a
production request loads: the AOP cache (woven classes, proxies, advisor
caches and metadata), Composer's class map and autoloaded files, the sources
below the include paths that are not woven, the loader file and the entry
points given with <info>--entry</info>. A new PHP-FPM master or CLI process then
loads compiled scripts from disk instead of compiling them.

Compiled scripts are written to disk only by a process started with the file
cache settings, which can not be changed at runtime:

  <info>mkdir -p /var/cache/opcache && php -d opcache.enable_cli=1 -d opcache.file_cache_only=1 \
      -d opcache.file_cache=/var/cache/opcache \
      vendor/bin/aspect %command.name% public/index.php --file-cache=/var/cache/opcache --entry=public/index.php</info>

<info>--file-cache=DIR</info> checks that the process writes to that directory; without
it the configured directory is used. The file cache belongs to the PHP build and
to the absolute paths of the files: run the command in the final image, after
<info>cache:warmup:aop</info>, with the PHP binary that serves the requests.

The AOP cache, the loader and the entry points must compile; files of the
Composer class map that do not (vendor test stubs written for a newer PHP) are
listed and skipped. <info>-v</info> lists every file.
EOT,
)]
class OpcacheWarmupCommand extends BaseAspectCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('file-cache', null, InputOption::VALUE_REQUIRED, 'Directory of the opcache file cache the process must write to');
        $this->addOption(
            'entry',
            null,
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Entry point to compile as well (front controller, console), may be repeated',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fileCacheDir = $input->getOption('file-cache');
        if ($fileCacheDir !== null && !is_string($fileCacheDir)) {
            throw InvalidConfigurationException::optionNotPath('file-cache');
        }
        $entryFiles = [];
        foreach ((array) $input->getOption('entry') as $entry) {
            if (!is_string($entry)) {
                throw InvalidConfigurationException::optionNotPath('entry');
            }
            $entryFile = realpath($entry);
            if ($entryFile === false || !is_readable($entryFile)) {
                throw InvalidConfigurationException::entryFileNotFound($entry);
            }
            $entryFiles[] = $entryFile;
        }

        $problem = $this->fileCacheProblem($fileCacheDir);
        if ($problem !== null) {
            // Written as plain lines, not thrown: the command line must stay copyable, the exception renderer wraps it
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errorOutput->writeln(sprintf('<error>Can not prime the opcache file cache: %s.</error>', $problem));
            $errorOutput->writeln('Compiled scripts land in the file cache only in a process started with these settings:');
            $errorOutput->writeln('  ' . FileCachePrimer::commandLine($fileCacheDir, self::arguments()));

            return Command::FAILURE;
        }
        $this->loadAspectKernel($input, $output);

        $cacheDir   = $this->aspectKernel->getOptions()['cacheDir'];
        $autoloader = ComposerAutoloader::ofProcess();
        // The AOP cache, the loader and the entry points must compile, the rest of the application is compiled when it can be
        $requiredFiles    = [...FileCachePrimer::cacheFiles($cacheDir ?? ''), ...($this->loaderPath !== null ? [$this->loaderPath] : []), ...$entryFiles];
        $applicationFiles = array_values(array_diff(FileCachePrimer::applicationFiles($this->aspectKernel, $autoloader), $requiredFiles));

        $directory = $fileCacheDir ?? (string) ini_get('opcache.file_cache');
        $output->writeln(sprintf(
            'Priming the opcache file cache%s with %d files of the AOP cache and %d files of the application.',
            $directory !== '' ? sprintf(' <info>%s</info>', $directory) : '',
            count($requiredFiles),
            count($applicationFiles),
        ));

        [$compiledRequired, $failedRequired]       = $this->compile($requiredFiles, $output);
        [$compiledApplication, $failedApplication] = $this->compile($applicationFiles, $output);
        $compiled = $compiledRequired + $compiledApplication;

        $output->writeln(sprintf('Compiled <info>%d</info> files.', $compiled));
        $this->reportFailures($output, $failedRequired, 'Files of the AOP cache or entry points that could not be compiled:', 'error');
        $this->reportFailures($output, $failedApplication, 'Files of the application that could not be compiled (skipped):', 'comment');

        return $failedRequired === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Tells why compiled scripts would not land in the file cache of this process, null when they do
     *
     * A command that primes another way (tests, a wrapper that already checked) overrides this and compileFiles().
     */
    protected function fileCacheProblem(?string $fileCacheDir): ?string
    {
        return FileCachePrimer::configurationProblem($fileCacheDir);
    }

    /**
     * Compiles the files into the file cache of this process, see FileCachePrimer::compile()
     *
     * @param list<string>                   $files
     * @param Closure(string, ?string): void $onFile
     *
     * @return int Number of compiled files
     */
    protected function compileFiles(array $files, Closure $onFile): int
    {
        return new FileCachePrimer()->compile($files, $onFile);
    }

    /**
     * Compiles a group of files, listing every file with -v
     *
     * @param list<string> $files
     *
     * @return array{int, array<string, string>} Number of compiled files and the reason per file that failed
     */
    private function compile(array $files, OutputInterface $output): array
    {
        $failed   = [];
        $compiled = $this->compileFiles($files, static function (string $file, ?string $error) use ($output, &$failed): void {
            if ($error !== null) {
                $failed[$file] = $error;
            }
            if ($output->isVerbose()) {
                $output->writeln($error === null ? sprintf('  %s', $file) : sprintf('  <error>%s: %s</error>', $file, $error));
            }
        });

        return [$compiled, $failed];
    }

    /**
     * @param array<string, string> $failures File => reason
     */
    private function reportFailures(OutputInterface $output, array $failures, string $heading, string $style): void
    {
        if ($failures === []) {
            return;
        }
        $output->writeln(sprintf('<%s>%s</%s>', $style, $heading, $style));
        foreach ($failures as $file => $reason) {
            $output->writeln(sprintf('  %s: %s', $file, $reason));
        }
    }

    /**
     * Returns the script and the arguments this process was started with
     *
     * @return list<string>
     */
    private static function arguments(): array
    {
        $arguments = $_SERVER['argv'] ?? [];

        return is_array($arguments) ? array_values(array_filter($arguments, is_string(...))) : [];
    }
}
