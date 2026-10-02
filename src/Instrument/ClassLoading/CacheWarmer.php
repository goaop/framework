<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use ErrorException;
use Go\Core\AspectKernel;
use Go\Instrument\FileSystem\Enumerator;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function count;

/**
 * Warms up the cache
 */
class CacheWarmer
{
    /**
     * Whether the warmup loop was asked to stop (e.g. by a signal handler)
     */
    private bool $interrupted = false;

    /**
     * CacheWarmer constructor.
     *
     * @param AspectKernel    $aspectKernel Instance of aspect kernel
     * @param OutputInterface $output       Output instance, errors go to its error output when it has one
     * @param bool            $failFast     Whether to stop after the first file that fails to process
     */
    public function __construct(
        protected AspectKernel $aspectKernel,
        protected OutputInterface $output = new NullOutput(),
        protected bool $failFast = false,
    ) {}

    /**
     * Asks the warmup loop to stop cleanly before processing the next file
     */
    public function interrupt(): void
    {
        $this->interrupted = true;
    }

    /**
     * Warms up cache
     *
     * @return int Number of files that failed to process
     */
    public function warmUp(): int
    {
        $options = $this->aspectKernel->getOptions();

        if (empty($options['cacheDir'])) {
            throw new InvalidArgumentException('Cache warmer require the `cacheDir` options to be configured');
        }

        // The transformation pipeline is registered lazily; the filter URIs built below
        // bypass FilterInjectorTransformer::rewrite(), so bring it up explicitly
        SourceTransformingLoader::ensureRegistered($this->aspectKernel->getContainer());

        $enumerator = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths']);
        $iterator   = $enumerator->enumerate();
        $total      = iterator_count($iterator);

        $this->output->writeln(sprintf('Total <info>%s</info> files to process.', $total));
        $this->output->writeln('');
        $iterator->rewind();

        $errorOutput = $this->output instanceof ConsoleOutputInterface ? $this->output->getErrorOutput() : $this->output;

        $errors    = [];
        $processed = 0;

        // Warnings raised while weaving fail the file, unless they are silenced by error_reporting() or `@`
        set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if ((error_reporting() & $errno) === 0) {
                return false;
            }
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        try {
            foreach ($iterator as $file) {
                if ($this->interrupted) {
                    $this->output->writeln('<comment>[STOP]: Warmup was interrupted, stopping...</comment>');
                    break;
                }

                $path = $file->getRealPath();
                $processed++;

                try {
                    // This will trigger creation of cache
                    file_get_contents(
                        FilterInjectorTransformer::PHP_FILTER_READ
                        . SourceTransformingLoader::FILTER_IDENTIFIER
                        . '/resource=' . $path,
                    );

                    $this->output->writeln(sprintf('<fg=green;options=bold>[OK]</>: <comment>%s</comment>', $path));
                } catch (Throwable $e) {
                    $errors[$path] = $e->getMessage();
                    $errorOutput->writeln(sprintf(
                        '<fg=white;bg=red;options=bold>[ERR]</>: %s: %s',
                        $path,
                        OutputFormatter::escape($e->getMessage()),
                    ));
                    if ($this->failFast) {
                        $this->interrupt();
                    }
                }
            }
        } finally {
            restore_error_handler();
        }

        $this->output->writeln('');
        $this->output->writeln(sprintf('<fg=green;>[DONE]</>: Total processed %s, %s errors.', $processed, count($errors)));

        return count($errors);
    }
}
