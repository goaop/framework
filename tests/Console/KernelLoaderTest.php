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

namespace Go\Console;

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Core\AspectKernel;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class KernelLoaderTest extends TestCase
{
    public function testUnreadableLoaderIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/^Loader file \/missing\/loader\.php does not exist or is not readable$/');

        KernelLoader::resolve('/missing/loader.php');
    }

    /**
     * Separate process: the kernel class must not be loaded yet
     */
    #[RunInSeparateProcess]
    public function testLoaderThatDoesNotBootTheKernelIsRejected(): void
    {
        if (class_exists(AspectKernel::class, false)) {
            $this->markTestSkipped('The kernel class was loaded before the test');
        }

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/^Aspect kernel was not initialized by the loader .*no-kernel\.php/');

        KernelLoader::load(__DIR__ . '/../Fixtures/warmup/no-kernel.php');
    }
}
