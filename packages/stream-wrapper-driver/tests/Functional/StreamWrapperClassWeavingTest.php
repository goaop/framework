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
 * The class weaving suite of the framework, run through the stream wrapper driver in debug mode: the same
 * transformer chain, so every join point of the fixture project is woven alike (the woven classes run in
 * subprocesses booting the driver).
 *
 * Every test runs in a process of its own: ReflectionFilenameTest of the core suite boots a kernel inside the test
 * runner and weaves the fixture class Main there, after which native reflection reports the proxy of the `aspect`
 * cache as its file and the woven-constraints look up the proxy of this configuration under a wrong path.
 */
#[RunTestsInSeparateProcesses]
final class StreamWrapperClassWeavingTest extends ClassWeavingTest
{
    protected function getConfigurationName(): string
    {
        return 'stream_wrapper';
    }
}
