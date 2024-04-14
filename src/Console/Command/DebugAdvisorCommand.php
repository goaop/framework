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
use Go\Core\AdviceMatcher;
use Go\Core\AspectContainer;
use Go\Core\CachedAspectLoader;
use Go\Instrument\FileSystem\Enumerator;
use Go\ParserReflection\ReflectionFile;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to debug an advisors
 *
 * @codeCoverageIgnore
 */
class DebugAdvisorCommand extends BaseAspectCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('debug:advisor')
            ->addOption('advisor', null, InputOption::VALUE_OPTIONAL, 'Identifier of advisor')
            ->setDescription('Provides an interface for checking and debugging advisors')
            ->setHelp(
                <<<EOT
Allows to query an information about matching joinpoints for specified advisor.
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
        $io->title('Advisor debug information');

        $advisorId = $input->getOption('advisor');
        if (empty($advisorId)) {
            $this->showAdvisorsList($io);
        } else {
            assert(is_string($advisorId), "Option 'advisor' must be a string, " . gettype($advisorId) . " given");
            $this->showAdvisorInformation($io, $advisorId);
        }

        return 0;
    }

    private function showAdvisorsList(SymfonyStyle $io): void
    {
        $io->writeln('List of registered advisors in the container');

        $aspectContainer = $this->aspectKernel->getContainer();
        $advisors        = $this->loadAdvisorsList($aspectContainer);

        $tableRows = [];
        foreach ($advisors as $id => $advisor) {
            $advice     = $advisor->getAdvice();
            $expression = '';
            try {
                $pointcutExpression = new ReflectionProperty($advice, 'pointcutExpression');
                $expression = $pointcutExpression->getValue($advice);
            } catch (ReflectionException $e) {
                // nothing here, just ignore
            }
            $tableRows[] = [$id, $expression];
        }
        $io->table(['Id', 'Expression'], $tableRows);

        $io->writeln(
            [
                'If you want to query an information about concrete advisor, then just query it',
                'by adding <info>--advisor="Advisor\\Name"</info> to the command'
            ]
        );
    }

    private function showAdvisorInformation(SymfonyStyle $io, string $advisorId): void
    {
        $aspectContainer = $this->aspectKernel->getContainer();

        $adviceMatcher = $aspectContainer->getService(AdviceMatcher::class);
        $this->loadAdvisorsList($aspectContainer);

        $advisor = $aspectContainer->getValue($advisorId);
        if (!$advisor instanceof Advisor) {
            throw new \InvalidArgumentException("Invalid advisor {$advisorId} given");
        }
        $options = $this->aspectKernel->getOptions();

        $enumerator = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths']);

        $iterator   = $enumerator->enumerate();
        $totalFiles = iterator_count($iterator);
        $io->writeln("Total <info>{$totalFiles}</info> files to analyze.");
        $iterator->rewind();

        foreach ($iterator as $file) {
            $reflectionFile       = new ReflectionFile((string)$file);
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
    }

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
