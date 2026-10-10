<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Functional;

/**
 * Same as MultiClassFileTest with debug mode off: the runtime class map maps every woven class of the file to its
 * woven file, so a class composer can not locate by its name is loaded first
 */
class ProductionMultiClassFileTest extends MultiClassFileTest
{
    protected function getFirstLoadedClass(): string
    {
        // Declared in MultiClassHolder.php: not discoverable by name for static analysis
        return 'Go\\Tests\\TestProject\\Application\\Sub\\MultiClassThird';
    }

    protected function getConfigurationName(): string
    {
        return 'production';
    }
}
