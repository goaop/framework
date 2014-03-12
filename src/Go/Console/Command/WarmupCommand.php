<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Command;

use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command for warming the cache
 */
class WarmupCommand extends Command
{

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('goaop:warmup')
            ->addArgument('loader', InputArgument::REQUIRED, "Path to the aspect loader file")
            ->setDescription("Warm up the cache with woven aspects")
            ->setHelp(<<<EOT
Initializes the kernel and, if successful, warm up the cache for PHP
files under the application directory.

By default, the cache directory is taken from configured AspectKernel class.
EOT
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Loading aspect kernel for warmup...");
        $loader = $input->getArgument('loader');
        $path   = stream_resolve_include_path($loader);
        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Invalid loader path: {$loader}");
        }
        include_once $path;

        if (!class_exists('Go\Core\AspectKernel', false)) {
            $message = "Kernel was not initialized yet, please configure it in the {$path}";
            throw new \InvalidArgumentException($message);
        }

        $kernel  = AspectKernel::getInstance();
        $options = $kernel->getOptions();

        if (empty($options['cacheDir'])) {
            throw new \InvalidArgumentException("Cache warmer require the `cacheDir` options to be configured");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $options['appDir'],
                \FilesystemIterator::SKIP_DOTS
            )
        );

        /** @var \CallbackFilterIterator|\SplFileInfo[] $iterator */
        $iterator   = new \CallbackFilterIterator($iterator, $this->getFileFilter($options));
        $totalFiles = iterator_count($iterator);
        $output->writeln("Total <info>{$totalFiles}</info> files to process.");
        $iterator->rewind();

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        });

        $index  = 0;
        $errors = array();
        foreach ($iterator as $file) {
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln("Processing file <info>{$file->getRealPath()}</info>");
            }
            $isSuccess = null;
            try {
                // This will trigger creation of cache
                file_get_contents(
                    FilterInjectorTransformer::PHP_FILTER_READ.
                    SourceTransformingLoader::FILTER_IDENTIFIER.
                    "/resource=" . $file->getRealPath()
                );
                $isSuccess = true;
            } catch (\Exception $e) {
                $isSuccess = false;
                $errors[$file->getRealPath()] = $e;
            }
            if ($output->getVerbosity() == OutputInterface::VERBOSITY_NORMAL) {
                $output->write($isSuccess ? '.' : '<error>E</error>');
                if (++$index % 50 == 0) {
                    $output->writeln("($index/$totalFiles)");
                }
            }
        }

        restore_error_handler();

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            foreach ($errors as $file=>$error) {
                $message = "File {$file} is not processed correctly due to exception: {$error->getMessage()}";
                $output->writeln($message);
            }
        }

        $output->writeln("<info>Done</info>");
    }

    /**
     * Filter for files
     *
     * @param array $options Kernel options
     *
     * @return callable
     */
    protected function getFileFilter(array $options)
    {
        $includePaths   = $options['includePaths'];
        $excludePaths   = $options['excludePaths'];
        $excludePaths[] = $options['cacheDir'];

        return function (\SplFileInfo $file) use ($includePaths, $excludePaths) {
            if ($file->getExtension() !== 'php') {
                return false;
            };

            if ($includePaths) {
                $found = false;
                foreach ($includePaths as $includePath) {
                    if (strpos($file->getRealPath(), realpath($includePath)) === 0) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return false;
                }
            }

            foreach ($excludePaths as $excludePath) {
                if (strpos($file->getRealPath(), realpath($excludePath)) === 0) {
                    return false;
                }
            }

            return true;
        };
    }
}
