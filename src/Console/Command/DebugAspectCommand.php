<?php
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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command for querying an information about aspects
 *
 * @codeCoverageIgnore
 */
class DebugAspectCommand extends BaseAspectCommand
{

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('debug:aspect')
            ->addOption('aspect', null, InputOption::VALUE_OPTIONAL, 'Optional aspect name to filter')
            ->setDescription('Provides an interface for querying the information about aspects')
            ->setHelp(<<<EOT
Allows to query an information about enabled aspects.
EOT
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadAspectKernel($input, $output);

        $io = new SymfonyStyle($input, $output);

        $container = $this->aspectKernel->getContainer();
        $aspects   = [];
        $io->title('Aspect debug information');

        $aspectName = $input->getOption('aspect');
        if (!$aspectName) {
            $io->text('<info>' . get_class($this->aspectKernel) . '</info> has following enabled aspects:');
            $aspects = $container->getByTag('aspect');
        } else {
            $aspect    = $container->getAspect($aspectName);
            $aspects[] = $aspect;
        }
        $this->showRegisteredAspectsInfo($io, $aspects);
    }

    /**
     * Shows an information about registered aspects
     *
     * @param SymfonyStyle $io Input-output style
     * @param array|Aspect[] $aspects List of aspects
     */
    private function showRegisteredAspectsInfo(SymfonyStyle $io, array $aspects)
    {
        foreach ($aspects as $aspect) {
            $this->showAspectInfo($io, $aspect);
        }
    }

    /**
     * Displays an information about single aspect
     *
     * @param SymfonyStyle $io Input-output style
     * @param Aspect $aspect Instance of aspect
     */
    private function showAspectInfo(SymfonyStyle $io, Aspect $aspect)
    {
        $refAspect  = new \ReflectionObject($aspect);
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
     *
     * @param SymfonyStyle $io Input-output style
     * @param Aspect $aspect Instance of aspect to query information
     */
    private function showAspectPointcutsAndAdvisors(SymfonyStyle $io, Aspect $aspect)
    {
        /** @var AspectLoader $aspectLoader */
        $container    = $this->aspectKernel->getContainer();
        $aspectLoader = $container->get('aspect.loader');
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
     *
     * @param string $comment
     *
     * @return string
     */
    private function getPrettyText($comment)
    {
        $text = preg_replace('|^\s*/?\*+/?|m', '', $comment);

        return $text;
    }
}
