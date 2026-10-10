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

namespace Go\Instrument\ZEngine\InProcess;

use Go\Aop\Exception\UnsupportedJoinpointException;
use Go\Instrument\ZEngine\MethodTableRewriter;
use Go\Stubs\ZEngine\RewriterDonor;
use Go\Stubs\ZEngine\RewriterTarget;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionException;

/**
 * The engine refusals the rewriter translates for the driver
 */
#[Group('zengine')]
final class MethodTableRewriterInProcessTest extends ZEngineInProcessTestCase
{
    public function testAClassMissingFromTheEngineClassTableIsRefused(): void
    {
        $this->bootKernel();
        $this->assertTrue(class_exists(RewriterDonor::class));

        try {
            // @phpstan-ignore argument.type (a class name the engine does not know is what the test needs)
            new MethodTableRewriter()->rewire('Go\Stubs\ZEngine\NeverDeclared', RewriterDonor::class, ['run']);
            self::fail('A class the engine does not know cannot be rewired');
        } catch (UnsupportedJoinpointException $refusal) {
            $this->assertStringContainsString('Go\Stubs\ZEngine\NeverDeclared', $refusal->getMessage());
            $this->assertStringContainsString('not published in the engine class table', $refusal->getMessage());
            $this->assertInstanceOf(ReflectionException::class, $refusal->getPrevious());
        }
    }

    public function testADonorWithoutTheMethodIsRefusedAndTheClassStaysUntouched(): void
    {
        $this->bootKernel();
        $target = new RewriterTarget();

        try {
            new MethodTableRewriter()->rewire(RewriterTarget::class, RewriterDonor::class, ['run']);
            self::fail('A donor without the dispatcher method cannot be adopted');
        } catch (UnsupportedJoinpointException $refusal) {
            $this->assertStringContainsString(RewriterTarget::class, $refusal->getMessage());
            $this->assertInstanceOf(ReflectionException::class, $refusal->getPrevious());
        }
        $this->assertSame('ran', $target->run());
        $this->assertFalse(new ReflectionClass($target)->hasMethod('runOriginalAlias'));
    }
}
