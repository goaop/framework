<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/demos',
    ])
    // Same "source shape is the test subject" exclusions as phpstan.neon:
    // weaving-input fixtures/stubs and their generated woven/proxy snapshots
    // must stay byte-identical, so the fixer must never touch them.
    ->exclude([
        'Instrument/Transformer/_files',
        'Instrument/Transformer/Stubs',
        'Stubs',
        'Fixtures',
        // Runtime-generated weaving cache of the demo application.
        'cache',
    ])
    ->append([
        __DIR__ . '/bin/aspect',
    ]);

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    // Risky mode is enabled solely for declare_strict_types below;
    // keep any additional risky rules out of this config.
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS' => true,
        'declare_strict_types' => true,
    ])
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setFinder($finder);
