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
use Go\Aop\Framework\AbstractInterceptor;
use Go\Core\AdviceMatcher;
use Go\Core\AspectContainer;
use Go\Core\Cache\CachedAspectLoader;
use Go\Instrument\FileSystem\Enumerator;
use Go\ParserReflection\ReflectionFile;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to debug an advisors
 */
#[AsCommand(
    name: 'debug:advisor',
    description: 'Provides an interface for checking and debugging advisors',
    help: <<<EOT
Lists the advisors registered in the aspect kernel, or shows which classes and members one advisor matches.

  <info>%command.full_name% web/index.php</info>
  <info>%command.full_name% web/index.php --advisor='App\Aspect\LoggingAspect->beforeMethod'</info>

The loader file is executed to boot the kernel. With <comment>--advisor</comment> every file below the kernel's
include paths is analysed; an unknown advisor id is reported as an error.
EOT,
)]
class DebugAdvisorCommand extends BaseAspectCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->addOption('advisor', null, InputOption::VALUE_REQUIRED, 'Show the joinpoints matched by this advisor (id from the list)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->loadAspectKernel($input, $output);

        $io = new SymfonyStyle($input, $output);
        $io->title('Advisor debug information');

        $advisorId = $input->getOption('advisor');
        if (!is_string($advisorId) || $advisorId === '') {
            $this->showAdvisorsList($io);

            return Command::SUCCESS;
        }

        return $this->showAdvisorInformation($io, $advisorId) ? Command::SUCCESS : Command::FAILURE;
    }

    private function showAdvisorsList(SymfonyStyle $io): void
    {
        $io->writeln('List of registered advisors in the container');

        $aspectContainer = $this->aspectKernel->getContainer();
        $advisors        = $this->loadAdvisorsList($aspectContainer);

        $tableRows = [];
        foreach ($advisors as $id => $advisor) {
            $advice      = $advisor->getAdvice();
            $expression  = $advice instanceof AbstractInterceptor ? $advice->pointcutExpression : '';
            $tableRows[] = [$id, $expression];
        }
        $io->table(['Id', 'Expression'], $tableRows);

        $io->writeln(
            [
                'If you want to query an information about concrete advisor, then just query it',
                'by adding <info>--advisor="Advisor\\Name"</info> to the command',
            ],
        );
    }

    /**
     * Shows the joinpoints matched by the advisor, returns false when there is no such advisor
     */
    private function showAdvisorInformation(SymfonyStyle $io, string $advisorId): bool
    {
        $aspectContainer = $this->aspectKernel->getContainer();

        $adviceMatcher = $aspectContainer->getService(AdviceMatcher::class);
        $advisors      = $this->loadAdvisorsList($aspectContainer);

        $advisor = $advisors[$advisorId] ?? null;
        if (!$advisor instanceof Advisor) {
            $io->error(sprintf('Advisor "%s" is not registered.', $advisorId) . self::suggestAlternative($advisorId, array_keys($advisors)));

            return false;
        }
        $options = $this->aspectKernel->getOptions();

        $enumerator = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths']);

        $files      = iterator_to_array($enumerator->enumerate(), false);
        $totalFiles = count($files);
        $io->writeln("Total <info>{$totalFiles}</info> files to analyze.");

        foreach ($files as $file) {
            $reflectionFile       = new ReflectionFile((string) $file);
            $reflectionNamespaces = $reflectionFile->getFileNamespaces();
            foreach ($reflectionNamespaces as $reflectionNamespace) {
                foreach ($reflectionNamespace->getClasses() as $reflectionClass) {
                    $advices = $adviceMatcher->getAdvicesForClass($reflectionClass, [$advisorId => $advisor]);
                    if (!empty($advices)) {
                        $this->writeInfoAboutAdvices($io, $reflectionClass, $advices);
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param ReflectionClass<covariant object> $reflectionClass
     * @param array<string, array<string, mixed>> $advices
     */
    private function writeInfoAboutAdvices(SymfonyStyle $io, ReflectionClass $reflectionClass, array $advices): void
    {
        $className = $reflectionClass->getName();
        foreach ($advices as $type => $typedAdvices) {
            foreach ($typedAdvices as $pointName => $advice) {
                $io->writeln("  -> matching <comment>{$type} {$className}->{$pointName}</comment>");
            }
        }
    }

    /**
     * Collects list of advisors from the given aspect container
     *
     * @return Advisor[] List of advisors in the container
     */
    private function loadAdvisorsList(AspectContainer $aspectContainer): array
    {
        $aspectLoader = $aspectContainer->getService(CachedAspectLoader::class);
        $aspects      = $aspectLoader->getUnloadedAspects();
        foreach ($aspects as $aspect) {
            $aspectLoader->loadAndRegister($aspect);
        }
        return $aspectContainer->getServicesByInterface(Advisor::class);
    }
}
