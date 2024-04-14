<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Command;

use FilesystemIterator;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\CacheWarmer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command for debugging weaving issues due to circular dependencies.
 *
 * @codeCoverageIgnore
 */
class DebugWeavingCommand extends BaseAspectCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('debug:weaving')
            ->setDescription('Checks consistency in weaving process')
            ->setHelp(
                <<<EOT
Allows to check consistency of weaving process, detects circular references and mutual dependencies between
subjects of weaving and aspects.
EOT
            )
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->loadAspectKernel($input, $output);

        $io = new SymfonyStyle($input, $output);

        $io->title('Weaving debug information');

        $cachePathManager = $this->aspectKernel->getContainer()->getService(CachePathManager::class);
        $warmer           = new CacheWarmer($this->aspectKernel, new NullOutput());
        $warmer->warmUp();

        $proxies = $this->getProxies($cachePathManager);

        $cachePathManager->clearCacheState();
        $warmer->warmUp();

        $errors = 0;

        foreach ($this->getProxies($cachePathManager) as $path => $content) {
            if (!isset($proxies[$path])) {
                $io->error(sprintf('Proxy on path "%s" is generated on second "warmup" pass.', $path));
                $errors++;
                continue;
            }

            if (isset($proxies[$path]) && $proxies[$path] !== $content) {
                $io->error(sprintf('Proxy on path "%s" is weaved differnlty on second "warmup" pass.', $path));
                $errors++;
                continue;
            }

            $io->note(sprintf('Proxy  on path "%s" is consistently weaved.', $path));
        }

        if ($errors > 0) {
            $io->error(sprintf('Weaving is unstable, there are %s reported error(s).', $errors));

            return $errors;
        }

        $io->success('Weaving is stable, there are no errors reported.');

        return 0;
    }

    /**
     * Gets Go! AOP generated proxy classes (paths and their contents) from the cache.
     */
    private function getProxies(CachePathManager $cachePathManager): array
    {
        $path     = $cachePathManager->getCacheDir() . '/_proxies';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $proxies = [];

        /**
         * @var SplFileInfo $splFileInfo
         */
        foreach ($iterator as $splFileInfo) {
            if ($splFileInfo->isFile()) {
                $proxies[$splFileInfo->getPathname()] = file_get_contents($splFileInfo->getPathname());
            }
        }

        return $proxies;
    }
}
