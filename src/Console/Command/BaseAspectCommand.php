<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Command;

use Go\Core\AspectKernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command for all aspect commands
 */
class BaseAspectCommand extends Command
{
    /**
     * Stores an instance of aspect kernel
     *
     * @var null|AspectKernel
     */
    protected $aspectKernel = null;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addArgument('loader', InputArgument::REQUIRED, "Path to the aspect loader file");
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loader = $input->getArgument('loader');
        $path   = stream_resolve_include_path($loader);
        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Invalid loader path: {$loader}");
        }
        include_once $path;

        if (!class_exists(AspectKernel::class, false)) {
            $message = "Kernel was not initialized yet, please configure it in the {$path}";
            throw new \InvalidArgumentException($message);
        }

        $this->aspectKernel = AspectKernel::getInstance();
    }
}
