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

use Go\Instrument\ClassLoading\CacheWarmer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command for warming the cache
 *
 * @codeCoverageIgnore
 */
class CacheWarmupCommand extends BaseAspectCommand
{

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('cache:warmup:aop')
            ->setDescription('Warm up the cache with woven aspects')
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
        $this->loadAspectKernel($input, $output);

        $warmer = new CacheWarmer($this->aspectKernel, $output);
        $warmer->warmUp();
    }
}
