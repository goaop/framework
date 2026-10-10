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

namespace Go\Aop\Exception;

use PHPUnit\Framework\TestCase;
use ReflectionException;

final class UnsupportedJoinpointExceptionTest extends TestCase
{
    public function testNamesTheAdvisorsAndKindsTheDriverCannotWeave(): void
    {
        $exception = UnsupportedJoinpointException::forClass('App\Entity', ['prop', 'init'], ['advisor.A->first', 'advisor.B->second']);

        $this->assertStringContainsString('cannot weave App\Entity', $exception->getMessage());
        $this->assertStringContainsString('advisor.A->first, advisor.B->second', $exception->getMessage());
        $this->assertStringContainsString('join point kind(s) prop, init', $exception->getMessage());
        $this->assertStringContainsString('stream driver', $exception->getMessage());
    }

    public function testWrapsAnEngineRefusal(): void
    {
        $reason    = new ReflectionException('Class App\Entity is not published in the engine class table');
        $exception = UnsupportedJoinpointException::engineRefusal('App\Entity', $reason);

        $this->assertSame($reason, $exception->getPrevious());
        $this->assertSame('The zengine weaving driver cannot weave App\Entity: ' . $reason->getMessage(), $exception->getMessage());
    }
}
