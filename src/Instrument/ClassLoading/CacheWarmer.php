<?php

declare(strict_types = 1);
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
     * Instance of aspect kernel
     */
    protected AspectKernel $aspectKernel;

    /**
     * Output instance
     */
    protected OutputInterface $output;

    /**
     * CacheWarmer constructor.
     */
    public function __construct(AspectKernel $aspectKernel, OutputInterface $output = null)
    {
        $this->aspectKernel = $aspectKernel;
        $this->output       = $output ?? new NullOutput();
    }

    /**
     * Warms up cache
     */
    public function warmUp(): void
    {
        $options = $this->aspectKernel->getOptions();

        if (empty($options['cacheDir'])) {
            throw new InvalidArgumentException('Cache warmer require the `cacheDir` options to be configured');
        }

        $enumerator = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths']);
        $iterator   = $enumerator->enumerate();
        $total      = iterator_count($iterator);

        $this->output->writeln(sprintf('Total <info>%s</info> files to process.', $total));
        $this->output->writeln('');
        $iterator->rewind();

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        });

        $errors = [];

        $displayException = function (Throwable $exception, string $path) use (&$errors) {
            $this->output->writeln(sprintf('<fg=white;bg=red;options=bold>[ERR]</>: %s', $path));
            $errors[$path] = $exception->getMessage();
        };

        foreach ($iterator as $file) {
            $path = $file->getRealPath();

            try {
                // This will trigger creation of cache
                file_get_contents(FilterInjectorTransformer::PHP_FILTER_READ .
                    SourceTransformingLoader::FILTER_IDENTIFIER .
                    '/resource=' . $path
                );

                $this->output->writeln(sprintf('<fg=green;options=bold>[OK]</>: <comment>%s</comment>', $path));
            } catch (Throwable $e) {
                $displayException($e, $path);
            }
        }

        restore_error_handler();

        if ($this->output->isVerbose()) {
            foreach ($errors as $path => $error) {
                $this->output->writeln(sprintf('<fg=white;bg=red;options=bold>[ERR]</>: File "%s" is not processed correctly due to exception: "%s".', $path, $error));
            }
        }

        $this->output->writeln('');
        $this->output->writeln(sprintf('<fg=green;>[DONE]</>: Total processed %s, %s errors.', $total, count($errors)));
    }
}
