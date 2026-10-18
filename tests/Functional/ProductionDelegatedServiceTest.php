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
 * Same as DelegatedServiceTest with debug mode off: woven code and advisors are served from the warmed-up cache
 */
class ProductionDelegatedServiceTest extends DelegatedServiceTest
{
    protected function getConfigurationName(): string
    {
        return 'production';
    }
}
