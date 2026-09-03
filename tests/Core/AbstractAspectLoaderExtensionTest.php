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

namespace Go\Core;

use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\PointcutGrammar;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use UnexpectedValueException;

class AbstractAspectLoaderExtensionTest extends TestCase
{
    private AbstractAspectLoaderExtensionTestExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new AbstractAspectLoaderExtensionTestExtension(
            new PointcutLexer(),
            new PointcutParser(new PointcutGrammar()),
        );
    }

    public function testParsesValidPointcutExpressionForMethodReflection(): void
    {
        $aspect     = new AbstractAspectLoaderExtensionTestAspect();
        $reflection = new ReflectionMethod($aspect, 'someMethod');

        $pointcut = $this->extension->doParsePointcut($aspect, $reflection, 'execution(public Foo->bar(*))');

        $this->assertInstanceOf(Pointcut::class, $pointcut);
    }

    public function testResolvesThisInPointcutExpressionToAspectClassName(): void
    {
        $aspect     = new AbstractAspectLoaderExtensionTestAspect();
        $reflection = new ReflectionMethod($aspect, 'someMethod');

        // "$this" must be replaced with the aspect's own class name before lexing
        $pointcut = $this->extension->doParsePointcut(
            $aspect,
            $reflection,
            'execution(public $this->someMethod(*))',
        );

        $this->assertInstanceOf(Pointcut::class, $pointcut);
    }

    public function testThrowsUnexpectedValueExceptionOnLexicalErrorForMethodReflection(): void
    {
        $aspect     = new AbstractAspectLoaderExtensionTestAspect();
        $reflection = new ReflectionMethod($aspect, 'someMethod');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/Can not recognize the lexical structure/');

        $this->extension->doParsePointcut($aspect, $reflection, 'execution(public Foo->@#$%^bar(*))');
    }

    public function testThrowsUnexpectedValueExceptionOnLexicalErrorForPropertyReflection(): void
    {
        $aspect     = new AbstractAspectLoaderExtensionTestAspect();
        $reflection = new ReflectionProperty($aspect, 'someProperty');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/Can not recognize the lexical structure/');

        $this->extension->doParsePointcut($aspect, $reflection, 'execution(public Foo->@#$%^bar(*))');
    }

    public function testThrowsUnexpectedValueExceptionOnLexicalErrorForClassReflection(): void
    {
        $aspect     = new AbstractAspectLoaderExtensionTestAspect();
        $reflection = new ReflectionClass($aspect);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/Can not recognize the lexical structure/');

        $this->extension->doParsePointcut($aspect, $reflection, 'execution(public Foo->@#$%^bar(*))');
    }

    public function testThrowsUnexpectedValueExceptionOnParserErrorForMethodReflection(): void
    {
        $aspect     = new AbstractAspectLoaderExtensionTestAspect();
        $reflection = new ReflectionMethod($aspect, 'someMethod');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/Unexpected token/');

        $this->extension->doParsePointcut($aspect, $reflection, 'public execution(*)');
    }

    public function testThrowsUnexpectedValueExceptionOnParserErrorForPropertyReflection(): void
    {
        $aspect     = new AbstractAspectLoaderExtensionTestAspect();
        $reflection = new ReflectionProperty($aspect, 'someProperty');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/Unexpected token/');

        $this->extension->doParsePointcut($aspect, $reflection, 'public execution(*)');
    }

    public function testThrowsUnexpectedValueExceptionOnParserErrorForClassReflection(): void
    {
        $aspect     = new AbstractAspectLoaderExtensionTestAspect();
        $reflection = new ReflectionClass($aspect);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/Unexpected token/');

        $this->extension->doParsePointcut($aspect, $reflection, 'public execution(*)');
    }
}

/**
 * Minimal concrete subclass exposing the protected parsePointcut() for direct testing
 */
final class AbstractAspectLoaderExtensionTestExtension extends AbstractAspectLoaderExtension implements AspectLoaderExtension
{
    public function load(Aspect $aspect, ReflectionClass $reflectionAspect): array
    {
        return [];
    }

    public function doParsePointcut(
        Aspect $aspect,
        ReflectionMethod|ReflectionProperty|ReflectionClass $reflection,
        string $pointcutExpression,
    ): Pointcut {
        return $this->parsePointcut($aspect, $reflection, $pointcutExpression);
    }
}

final class AbstractAspectLoaderExtensionTestAspect implements Aspect
{
    public $someProperty;

    public function someMethod(): void
    {
    }
}
