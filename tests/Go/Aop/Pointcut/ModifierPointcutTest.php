<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Stubs\FirstStatic;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

class ModifierPointcutTest extends TestCase
{
    private ModifierPointcut $pointcut;

    protected function setUp(): void
    {
        $this->pointcut = new ModifierPointcut();
    }

    /**
     * @param ReflectionClass<FirstStatic> $context
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('reflectorProvider')]
    public function testMatchesModifiers(
        int $orMask,
        int $andMask,
        int $notMask,
        ReflectionClass $context,
        ReflectionMethod $reflector,
    ): void {
        if ($orMask > 0) {
            $this->pointcut->orMatch($orMask);
        }
        if ($andMask > 0) {
            $this->pointcut->andMatch($andMask);
        }
        if ($notMask > 0) {
            $this->pointcut->notMatch($notMask);
        }

        $modifiers = $reflector->getModifiers();

        // If "not" isset and matches at least one modifier, this should never match at all
        if ($notMask & $modifiers) {
            $this->assertFalse($this->pointcut->matches($context, $reflector));
        } elseif ($orMask & $modifiers) {
            // If "or" mask is set, it is enough to match with at least one modifier
            $this->assertTrue($this->pointcut->matches($context, $reflector));
        } elseif ($andMask === ($andMask & $modifiers)) {
            // Otherwise we have strict "AND" comparison that should match
            $this->assertTrue($this->pointcut->matches($context, $reflector));
        } elseif ($andMask !== ($andMask & $modifiers)) {
            // But if mask for "AND" is not equal itself, then we have strict comparison that should not match
            $this->assertFalse($this->pointcut->matches($context, $reflector));
        } else {
            $this->fail('Unknown logical combination of modifiers');
        }
    }

    public static function reflectorProvider(): \Generator
    {
        $maskMatrix = [
            0,
            ReflectionMethod::IS_PUBLIC,
            ReflectionMethod::IS_PROTECTED,
            ReflectionMethod::IS_PRIVATE,
            ReflectionMethod::IS_STATIC,
            ReflectionMethod::IS_FINAL,
        ];
        $reflectionClass = new ReflectionClass(FirstStatic::class);

        // We can store known modifiers to avoid extra loops for known modifiers
        $knownModifiers = [];
        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $modifierMask = $reflectionMethod->getModifiers();
            if (in_array($modifierMask, $knownModifiers, true)) {
                // let's skip method if we have already tested another method with same modifier mask
                continue;
            } else {
                $knownModifiers[] = $modifierMask;
            }
            foreach ($maskMatrix as $orMask) {
                foreach ($maskMatrix as $andMask) {
                    foreach ($maskMatrix as $notMask) {
                        $orName   = $orMask ? "(OR=" . join('', \Reflection::getModifierNames($orMask)) . ")" : '';
                        $andName  = $andMask ? "(AND=" . join('', \Reflection::getModifierNames($andMask)) . ")" : '';
                        $notName  = $notMask ? "(NOT=" . join('', \Reflection::getModifierNames($notMask)) . ")" : '';
                        $name     = $reflectionMethod->getDeclaringClass()->getName() . '::' . $reflectionMethod->getName();
                        $key      = $name . $orName . $andName . $notName;
                        yield $key => [$orMask, $andMask, $notMask, $reflectionClass, $reflectionMethod];
                    }
                }
            }
        }
    }

    public function testAlwaysMatchesWithoutReflectorInstance(): void
    {
        $reflectionClass = new ReflectionClass(FirstStatic::class);
        $this->assertTrue($this->pointcut->matches($reflectionClass));
    }

    public function testNeverMatchesForFunctionModifiers(): void
    {
        $reflectionClass = new ReflectionClass(FirstStatic::class);
        $this->pointcut->andMatch(ReflectionMethod::IS_PUBLIC);

        $this->assertFalse($this->pointcut->matches(
            $reflectionClass,
            new ReflectionFunction('var_dump')
        ));
    }

    public function testGetKind(): void
    {
        $this->assertSame(Pointcut::KIND_ALL, $this->pointcut->getKind());
    }
}
