<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use PHPUnit\Framework\TestCase;

class AbstractInvocationTest extends TestCase
{
    private AbstractInvocation $invocation;

    protected function setUp(): void
    {
        $this->invocation = new class ([]) extends AbstractInvocation {
            public function proceed(): mixed
            {
                return null;
            }

            public function __toString(): string
            {
                return 'test-invocation';
            }
        };
    }

    public function testGetArgumentsReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], $this->invocation->getArguments());
    }

    public function testSetArgumentsMutatesArguments(): void
    {
        $this->invocation->setArguments(['a', 42, true]);

        $this->assertSame(['a', 42, true], $this->invocation->getArguments());
    }
}
