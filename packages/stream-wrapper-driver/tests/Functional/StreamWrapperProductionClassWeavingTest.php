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

namespace Go\Driver\StreamWrapper\Functional;

use Go\Functional\ClassWeavingTest;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Same as StreamWrapperClassWeavingTest with debug mode off: the woven files are served from the warmed cache
 * (process isolation for the same reason)
 */
#[RunTestsInSeparateProcesses]
final class StreamWrapperProductionClassWeavingTest extends ClassWeavingTest
{
    protected function getConfigurationName(): string
    {
        return 'stream_wrapper_production';
    }
}
