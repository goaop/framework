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

namespace Go\Proxy\Part;

use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Proxy\Generator\GeneratedCodePrinter;
use Go\Proxy\Generator\InterceptorListGenerator;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionMethod;

final class JoinPointStatementsGeneratorTest extends TestCase
{
    /**
     * The statements built as AST print exactly as the body source the generators parsed before
     */
    public function testStatementsPrintAsParsedBody(): void
    {
        $interceptors = [
            GeneratedInterceptor::fromAdvice('advisor.id', new BeforeInterceptor(static function (): void {})),
        ];
        $statements = [
            JoinPointStatementsGenerator::createInitialization(
                'DynamicMethodInvocation<self, int>',
                'InterceptorInjector',
                'forMethod',
                [
                    new Arg(new ClassConstFetch(new Name('self'), 'class')),
                    new Arg(new String_('publicMethod')),
                    new Arg((new InterceptorListGenerator($interceptors))->getNode()),
                    new Arg(new MethodCall(new Variable('this'), 'publicMethodOriginalAlias', [new VariadicPlaceholder()])),
                ],
            ),
            JoinPointStatementsGenerator::createInvocation(
                [new Arg(new Variable('this')), ...(new FunctionCallArgumentListGenerator(new ReflectionFunction('basename')))->getArgs()],
                true,
            ),
        ];
        $body = <<<'BODY'
            /** @var DynamicMethodInvocation<self, int> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forMethod(
                self::class,
                'publicMethod',
                [Interceptor::before(The::advice('advisor.id'))],
                $this->publicMethodOriginalAlias(...),
            );
            return $__joinPoint->__invoke($this, \array_slice([$path, $suffix], 0, \func_num_args()));
            BODY;
        $parsedStatements = (new ParserFactory())->createForNewestSupportedVersion()->parse('<?php ' . $body);
        $this->assertNotNull($parsedStatements);

        $printer = new GeneratedCodePrinter(['shortArraySyntax' => true]);
        $this->assertSame($printer->prettyPrint($parsedStatements), $printer->prettyPrint($statements));
        $this->assertSame(<<<'CODE'
            /** @var DynamicMethodInvocation<self, int> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forMethod(
                self::class,
                'publicMethod',
                [
                    Interceptor::before(The::advice('advisor.id')),
                ],
                $this->publicMethodOriginalAlias(...),
            );
            return $__joinPoint->__invoke($this, \array_slice([$path, $suffix], 0, \func_num_args()));
            CODE, $printer->prettyPrint($statements));
    }

    public function testInvocationWithoutResult(): void
    {
        $statement = JoinPointStatementsGenerator::createInvocation([new Arg(new Variable('this'))], false);

        $this->assertSame(
            '$__joinPoint->__invoke($this);',
            (new GeneratedCodePrinter())->prettyPrint([$statement]),
        );
    }

    public function testReturnsResultOfMethods(): void
    {
        $object = new class {
            public function __construct() {}

            public function __clone() {}

            // @phpstan-ignore missingType.return (a method without return type is the case under test)
            public function untyped() {}

            public function typed(): int
            {
                return 1;
            }

            public function nothing(): void {}

            public function fails(): never
            {
                throw new \LogicException();
            }
        };
        $expected = ['__construct' => false, '__clone' => false, 'untyped' => true, 'typed' => true, 'nothing' => false, 'fails' => false];
        foreach ($expected as $methodName => $returnsResult) {
            $this->assertSame(
                $returnsResult,
                JoinPointStatementsGenerator::returnsResult(new ReflectionMethod($object, $methodName)),
                $methodName,
            );
        }
    }

    public function testVoidAndNeverFunctionsReturnNothing(): void
    {
        $this->assertFalse(JoinPointStatementsGenerator::returnsResult(new ReflectionFunction('usleep')));
        $this->assertFalse(JoinPointStatementsGenerator::returnsResult(new ReflectionFunction('exit')));
        $this->assertTrue(JoinPointStatementsGenerator::returnsResult(new ReflectionFunction('strlen')));
    }
}
