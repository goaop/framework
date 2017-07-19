<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use Go\Core\AspectKernel;
use Go\Instrument\FileSystem\Enumerator;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Warms up the cache
 */
class CacheWarmer
{
    /**
     * @var AspectKernel
     */
    protected $aspectKernel;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * CacheWarmer constructor.
     *
     * @param AspectKernel $aspectKernel A kernel
     * @param OutputInterface $output Optional output to log current status.
     */
    public function __construct(AspectKernel $aspectKernel, OutputInterface $output = null)
    {
        $this->aspectKernel = $aspectKernel;
        $this->output = $output;
    }

    /**
     * Warms up cache
     */
    public function warmUp()
    {
        $options = $this->aspectKernel->getOptions();

        if (empty($options['cacheDir'])) {
            throw new \InvalidArgumentException('Cache warmer require the `cacheDir` options to be configured');
        }

        $enumerator = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths']);
        $iterator = $enumerator->enumerate();
        $total = iterator_count($iterator);

        $this->writeln(sprintf('Total <info>%s</info> files to process.', $total));
        $this->newline();
        $iterator->rewind();

        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        });

        $errors = [];

        foreach ($iterator as $file) {
            $path = $file->getRealPath();

            try {
                // This will trigger creation of cache
                file_get_contents(FilterInjectorTransformer::PHP_FILTER_READ .
                    SourceTransformingLoader::FILTER_IDENTIFIER .
                    '/resource=' . $path
                );

                $this->writeln(sprintf('<fg=green;options=bold>[OK]</>: <comment>%s</comment>', $path));
            } catch (\Exception $e) {
                $this->writeln(sprintf('<fg=white;bg=red;options=bold>[ERR]</>: %s', $path));
                $errors[$path] = $e->getMessage();
            }
        }

        restore_error_handler();

        if ($this->isVerbose()) {
            foreach ($errors as $path => $error) {
                $this->writeln(sprintf('<fg=white;bg=red;options=bold>[ERR]</>: File "%s" is not processed correctly due to exception: "%s".', $path, $error));
            }
        }

        $this->newline();
        $this->writeln(sprintf('<fg=green;>[DONE]</>: Total processed %s, %s errors.', $total, count($errors)));
    }

    /**
     * Proxy to OutputInterface->writeln() method dumping new line.
     */
    private function newline()
    {
        if (null === $this->output) {
            return;
        }

        $this->output->writeln('');
    }

    /**
     * Proxy to OutputInterface->writeln() method.
     *
     * @param string $message Message text.
     */
    private function writeln($message)
    {
        if (null === $this->output) {
            return;
        }

        $this->output->writeln($message);
    }

    /**
     * Check if output verbosity is verbose.
     *
     * @return bool
     */
    private function isVerbose()
    {
        if (null === $this->output) {
            return false;
        }

        return $this->output->isVerbose();
    }
}