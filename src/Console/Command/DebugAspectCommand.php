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

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use Go\Core\AspectLoader;
use ReflectionObject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command for querying an information about aspects
 */
#[AsCommand(
    name: 'debug:aspect',
    description: 'Provides an interface for querying the information about aspects',
    help: <<<EOT
Lists the aspects registered in the aspect kernel, with the pointcuts and advisors declared by each of them.

  <info>%command.full_name% web/index.php</info>
  <info>%command.full_name% web/index.php --aspect='App\Aspect\LoggingAspect'</info>

The loader file is executed to boot the kernel, so pass a file that initializes the kernel without handling
a request. With <comment>--aspect</comment> only that aspect is shown; an unknown aspect is reported as an error.
EOT,
)]
class DebugAspectCommand extends BaseAspectCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('aspect', null, InputOption::VALUE_REQUIRED, 'Show only this aspect (class name)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->loadAspectKernel($input, $output);

        $io = new SymfonyStyle($input, $output);

        $container = $this->aspectKernel->getContainer();
        $aspects   = [];
        $io->title('Aspect debug information');

        $aspectName = $input->getOption('aspect');
        if (!is_string($aspectName) || $aspectName === '') {
            $io->text('<info>' . $this->aspectKernel::class . '</info> has following enabled aspects:');
            $aspects = $container->getServicesByInterface(Aspect::class);
        } else {
            $aspectName = ltrim($aspectName, '\\');
            if (!$container->has($aspectName) || !is_subclass_of($aspectName, Aspect::class)) {
                $registeredAspects = array_keys($container->getServicesByInterface(Aspect::class));
                $io->error(sprintf('Aspect "%s" is not registered in the kernel.', $aspectName) . self::suggestAlternative($aspectName, $registeredAspects));

                return Command::FAILURE;
            }
            $aspects[] = $container->getService($aspectName);
        }
        $this->showRegisteredAspectsInfo($io, $aspects);

        return Command::SUCCESS;
    }

    /**
     * Shows an information about registered aspects
     *
     * @param Aspect[] $aspects List of aspects
     */
    private function showRegisteredAspectsInfo(SymfonyStyle $io, array $aspects): void
    {
        foreach ($aspects as $aspect) {
            $this->showAspectInfo($io, $aspect);
        }
    }

    /**
     * Displays an information about single aspect
     */
    private function showAspectInfo(SymfonyStyle $io, Aspect $aspect): void
    {
        $refAspect  = new ReflectionObject($aspect);
        $aspectName = $refAspect->getName();
        $io->section($aspectName);
        $io->writeln('Defined in: <info>' . $refAspect->getFileName() . '</info>');
        $docComment = $refAspect->getDocComment();
        if ($docComment) {
            $io->writeln($this->getPrettyText($docComment));
        }
        $this->showAspectPointcutsAndAdvisors($io, $aspect);
    }

    /**
     * Shows an information about aspect pointcuts and advisors
     */
    private function showAspectPointcutsAndAdvisors(SymfonyStyle $io, Aspect $aspect): void
    {
        $container = $this->aspectKernel->getContainer();

        $aspectLoader = $container->getService(AspectLoader::class);
        $io->writeln('<comment>Pointcuts and advices</comment>');

        $aspectItems     = $aspectLoader->load($aspect);
        $aspectItemsInfo = [];
        foreach ($aspectItems as $itemId => $item) {
            $itemType = 'Unknown';
            if ($item instanceof Pointcut) {
                $itemType = 'Pointcut';
            }
            if ($item instanceof Advisor) {
                $itemType = 'Advisor';
            }
            $aspectItemsInfo[] = [$itemType, $itemId];
        }
        $io->table(['Type', 'Identifier'], $aspectItemsInfo);
    }

    /**
     * Gets the reformatted comment text.
     */
    private function getPrettyText(string $comment): string
    {
        $text = preg_replace('|^\s*/?\*+/?|m', '', $comment);

        return $text ?? $comment;
    }
}
