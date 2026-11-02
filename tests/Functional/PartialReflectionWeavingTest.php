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

use Go\Tests\TestProject\Application\Issue251ReportController;

/**
 * A class whose ancestors can't be located is woven with its own methods instead of failing the file
 * (goaop/parser-reflection#251)
 */
class PartialReflectionWeavingTest extends BaseFunctionalTestCase
{
    private const string AROUND = 'Go\\Tests\\TestProject\\Aspect\\Issue251Aspect->aroundControllerMethod';

    /**
     * Declares the parent class and the interface of Issue251ReportController the way generated code does: at
     * runtime, without a source file Composer knows
     */
    private const string DECLARE_ANCESTORS = <<<'PHP'
        eval('namespace Go\Tests\TestProject\Generated;
            interface Issue251Controller {}
            abstract class Issue251BaseController { public function handle(): string { return "base"; } }');
        PHP;

    /**
     * The cache was warmed up in a process where the ancestors don't exist: the own method is woven, the inherited
     * one of the missing parent class is unknown to the weaver
     */
    public function testOwnMethodsOfClassWithUnlocatableAncestorsAreWoven(): void
    {
        $this->assertMethodWoven(Issue251ReportController::class, 'report', self::AROUND);
        $this->assertMethodNotWoven(Issue251ReportController::class, 'handle');
    }

    /**
     * The proxy keeps the parent class and the interface declared by the original class
     */
    public function testWovenClassKeepsItsUnlocatableAncestors(): void
    {
        $calls = $this->runAndRecordCalls(self::DECLARE_ANCESTORS . sprintf(
            '$controller = new %1$s();'
            . '$calls["report"] = [$controller->report(), %2$s::$calls];'
            . '$calls["ancestors"] = [get_parent_class($controller), class_implements($controller)];',
            '\\' . Issue251ReportController::class,
            '\\Go\\Tests\\TestProject\\Aspect\\Issue251Aspect',
        ));

        $this->assertSame(['report of base', ['around:report']], $calls['report']);
        $this->assertSame(
            [
                'Go\\Tests\\TestProject\\Generated\\Issue251BaseController',
                [
                    'Go\\Tests\\TestProject\\Generated\\Issue251Controller' => 'Go\\Tests\\TestProject\\Generated\\Issue251Controller',
                    'Go\\Aop\\Proxy'                                       => 'Go\\Aop\\Proxy',
                ],
            ],
            $calls['ancestors'],
        );
    }
}
