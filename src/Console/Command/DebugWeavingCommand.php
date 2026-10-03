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
use Go\Core\AspectContainer;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\PathResolver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command for debugging weaving issues due to circular dependencies.
 */
#[AsCommand(
    name: 'debug:weaving',
    description: 'Checks consistency in weaving process',
    help: <<<EOT
Weaves the whole application twice and compares the generated proxies: a proxy that only appears, or looks
different, on the second pass reveals circular references and mutual dependencies between woven classes and
aspects (for example an aspect that depends on a class it advises).

  <info>%command.full_name% web/index.php</info>
  <info>%command.full_name% web/index.php -v</info>   also prints the difference of each unstable proxy

The loader file is executed to boot the kernel, and the cache directory of the kernel is rewritten.
EOT,
)]
class DebugWeavingCommand extends BaseAspectCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->loadAspectKernel($input, $output);

        $io = new SymfonyStyle($input, $output);

        $io->title('Weaving debug information');

        $cachePathManager = $this->aspectKernel->getContainer()->getService(CachePathManager::class);
        $warmer           = $this->createCacheWarmer(new NullOutput());
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

            if ($proxies[$path] !== $content) {
                $io->error(sprintf('Proxy on path "%s" is weaved differently on second "warmup" pass.', $path));
                if ($output->isVerbose()) {
                    $io->writeln(self::diffLines($proxies[$path], $content));
                }
                $errors++;
                continue;
            }

            if ($output->isVeryVerbose()) {
                $io->note(sprintf('Proxy on path "%s" is consistently weaved.', $path));
            }
        }

        if ($errors > 0) {
            $io->error(sprintf('Weaving is unstable, there are %s reported error(s).', $errors));

            return Command::FAILURE;
        }

        $io->success('Weaving is stable, there are no errors reported.');

        return Command::SUCCESS;
    }

    /**
     * Returns a line diff of two proxy versions: removed lines are prefixed with "-", added lines with "+"
     *
     * @return list<string>
     */
    private static function diffLines(string $first, string $second): array
    {
        $firstLines  = explode("\n", $first);
        $secondLines = explode("\n", $second);
        $firstCount  = count($firstLines);
        $secondCount = count($secondLines);

        // Longest common subsequence table, proxies are small enough for the quadratic approach
        $lengths = array_fill(0, $firstCount + 1, array_fill(0, $secondCount + 1, 0));
        for ($i = $firstCount - 1; $i >= 0; $i--) {
            for ($j = $secondCount - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $firstLines[$i] === $secondLines[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $diff = [];
        [$i, $j] = [0, 0];
        while ($i < $firstCount || $j < $secondCount) {
            if ($i < $firstCount && $j < $secondCount && $firstLines[$i] === $secondLines[$j]) {
                [$i, $j] = [$i + 1, $j + 1];
            } elseif ($j < $secondCount && ($i === $firstCount || $lengths[$i][$j + 1] >= $lengths[$i + 1][$j])) {
                $diff[] = '<info>+ ' . $secondLines[$j++] . '</info>';
            } else {
                $diff[] = '<fg=red>- ' . $firstLines[$i++] . '</>';
            }
        }

        return $diff;
    }

    /**
     * Gets Go! AOP generated proxy classes (paths and their contents) from the cache.
     *
     * @return array<string, string>
     */
    private function getProxies(CachePathManager $cachePathManager): array
    {
        $path = $cachePathManager->getCacheDir();
        if ($path === null) {
            return [];
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS,
            ),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        $proxies = [];

        /**
         * @var SplFileInfo $splFileInfo
         */
        foreach ($iterator as $splFileInfo) {
            if (!$splFileInfo->isFile() || $splFileInfo->getExtension() !== 'php') {
                continue;
            }
            $pathname = $splFileInfo->getPathname();
            if (str_ends_with($pathname, AspectContainer::ORIGINAL_TRAIT_FILE_SUFFIX)) {
                continue;
            }
            // Only collect proxy files: they have a sibling `<Class>OriginalTrait.php` file
            $traitSibling = PathResolver::withSuffixBeforeExtension($pathname, AspectContainer::ORIGINAL_TRAIT_SUFFIX);
            if (!file_exists($traitSibling)) {
                continue;
            }
            $content = file_get_contents($pathname);
            if ($content !== false) {
                $proxies[$pathname] = $content;
            }
        }

        return $proxies;
    }
}
