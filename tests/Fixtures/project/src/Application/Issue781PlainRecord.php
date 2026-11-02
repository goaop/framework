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

namespace Go\Tests\TestProject\Application;

/**
 * Test fixture (issue #781): parent the pointcut does not match, so it is not woven
 */
class Issue781PlainRecord
{
    public function describe(): string
    {
        return 'record';
    }
}
