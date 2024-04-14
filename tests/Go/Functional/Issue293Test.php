<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Functional;

use Go\Tests\TestProject\Application\Issue293DynamicMembers;
use Go\Tests\TestProject\Application\Issue293StaticMembers;

class Issue293Test extends BaseFunctionalTestCase
{
    /**
     * test for https://github.com/goaop/framework/issues/293
     */
    public function testItDoesNotWeaveDynamicMethodsForComplexStaticPointcut()
    {
        $this->assertClassIsWoven(Issue293StaticMembers::class);
        $this->assertStaticMethodWoven(Issue293StaticMembers::class, 'doSomething', 'Go\\Tests\\TestProject\\Aspect\\Issue293Aspect->afterPublicOrProtectedStaticMethods');
        $this->assertStaticMethodWoven(Issue293StaticMembers::class, 'doSomethingElse', 'Go\\Tests\\TestProject\\Aspect\\Issue293Aspect->afterPublicOrProtectedStaticMethods');

        // it does not weaves Issue293DynamicMembers class
        $this->assertClassIsNotWoven(Issue293DynamicMembers::class);
    }
}
