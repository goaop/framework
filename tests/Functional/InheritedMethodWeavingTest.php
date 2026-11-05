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

use Go\Tests\TestProject\Application\Issue781AbstractGateway;
use Go\Tests\TestProject\Application\Issue781ArchivedRecordGateway;
use Go\Tests\TestProject\Application\Issue781CardGateway;
use Go\Tests\TestProject\Application\Issue781PlainRecord;
use Go\Tests\TestProject\Application\Issue781RecordGateway;

/**
 * An advice of an execution pointcut runs once per method execution, however many classes of the hierarchy are woven
 * (issue #781)
 */
class InheritedMethodWeavingTest extends BaseFunctionalTestCase
{
    private const string AROUND = 'Go\\Tests\\TestProject\\Aspect\\Issue781Aspect->aroundGatewayMethod';
    private const string BEFORE = 'Go\\Tests\\TestProject\\Aspect\\Issue781Aspect->beforeGatewayStaticMethod';

    /**
     * The parent intercepts the methods it declares, the child inherits the intercepted methods and overrides nothing
     */
    public function testChildDoesNotInterceptMethodsOfWovenParent(): void
    {
        $this->assertMethodWoven(Issue781AbstractGateway::class, 'count', self::AROUND);
        $this->assertMethodWoven(Issue781AbstractGateway::class, 'references', self::AROUND);
        $this->assertMethodWoven(Issue781AbstractGateway::class, '__construct', self::AROUND);
        $this->assertStaticMethodWoven(Issue781AbstractGateway::class, 'create', self::BEFORE);

        // Nothing is left to intercept in the child: it is not woven at all
        $this->assertClassIsNotWoven(Issue781CardGateway::class);
    }

    public function testAdviceRunsOncePerCallOfInheritedMethod(): void
    {
        $calls = $this->runAndRecordCalls(sprintf(
            '$gateway = %1$s::create("card"); $calls["create"] = %2$s::$calls; %2$s::$calls = [];'
            . '$gateway->count(); $calls["count"] = %2$s::$calls; %2$s::$calls = [];'
            . '$calls["references"] = $gateway->references(); $calls["references"] = [$calls["references"], %2$s::$calls];'
            . '$calls["class"] = $gateway::class;',
            '\\' . Issue781CardGateway::class,
            '\\Go\\Tests\\TestProject\\Aspect\\Issue781Aspect',
        ));

        $this->assertSame(['before:create', 'around:__construct'], $calls['create']);
        $this->assertSame(['around:count'], $calls['count']);
        $this->assertSame([['card'], ['around:references']], $calls['references']);
        // Late static binding of the static method intercepted in the parent still creates the child
        $this->assertSame(Issue781CardGateway::class, $calls['class']);
    }

    /**
     * A parent the pointcut does not match is not woven: the child intercepts the inherited method itself, its own
     * woven child relies on that interceptor
     */
    public function testInheritedMethodOfUnwovenParentIsInterceptedOnce(): void
    {
        $this->assertClassIsNotWoven(Issue781PlainRecord::class);
        $this->assertMethodWoven(Issue781RecordGateway::class, 'describe', self::AROUND);
        $this->assertMethodNotWoven(Issue781ArchivedRecordGateway::class, 'describe', self::AROUND);

        $calls = $this->runAndRecordCalls(sprintf(
            '$calls["record"] = [(new %1$s())->describe(), %3$s::$calls]; %3$s::$calls = [];'
            . '$calls["archived"] = [(new %2$s())->describe(), %3$s::$calls];',
            '\\' . Issue781RecordGateway::class,
            '\\' . Issue781ArchivedRecordGateway::class,
            '\\Go\\Tests\\TestProject\\Aspect\\Issue781Aspect',
        ));

        $this->assertSame(['record', ['around:describe']], $calls['record']);
        $this->assertSame(['record', ['around:describe']], $calls['archived']);
    }
}
