<?php

namespace Go\Functional;

use Go\Tests\TestProject\Application\Issue293DynamicMembers;
use Go\Tests\TestProject\Application\Issue293StaticMembers;

class Issue293Test extends BaseFunctionalTest
{
    /**
     * test for https://github.com/goaop/framework/issues/293
     */
    public function testItDoesNotWeaveDynamicMethodsForComplexStaticPointcut()
    {
        $this->assertClassIsWoven(Issue293StaticMembers::class);
        $this->assertStaticMethodJoinPointExists(Issue293StaticMembers::class, 'doSomething', 'advisor.Go\\Tests\\TestProject\\Aspect\\Issue293Aspect->afterPublicOrProtectedStaticMethods');
        $this->assertStaticMethodJoinPointExists(Issue293StaticMembers::class, 'doSomethingElse', 'advisor.Go\\Tests\\TestProject\\Aspect\\Issue293Aspect->afterPublicOrProtectedStaticMethods');

        // it does not weaves Issue293DynamicMembers class
        $this->assertClassIsNotWoven(Issue293DynamicMembers::class);
    }
}
