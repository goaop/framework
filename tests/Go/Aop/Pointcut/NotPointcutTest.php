<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class NotPointcutTest extends TestCase
{
    protected NotPointcut $pointcut;

    public function setUp(): void
    {
        $this->pointcut = new NotPointcut(new TruePointcut());
    }

    public function testItNeverMatchesForTruePointcut()
    {
        $this->assertFalse($this->pointcut->matches(null));
        $this->assertFalse($this->pointcut->matches(new ReflectionClass(self::class)));
    }
}
