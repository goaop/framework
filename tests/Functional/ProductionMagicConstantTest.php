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
 * Same as MagicConstantTest with debug mode off: includes inside woven files still go through the filter
 */
class ProductionMagicConstantTest extends MagicConstantTest
{
    protected function getConfigurationName(): string
    {
        return 'production';
    }
}
