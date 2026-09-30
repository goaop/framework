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

namespace Go\Proxy\Generator;

use Go\Aop\AspectException;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;

/**
 * Renders generated interceptor descriptors as Interceptor::* factory calls.
 *
 * Every class reference is emitted fully qualified, so the rendered code never depends on
 * the `use` imports of the file it is embedded into (those are copied from user code and
 * may bind the same short names to unrelated classes).
 *
 * @internal
 */
final class InterceptorListGenerator
{
    /**
     * Default continuation-line indentation for the rendered interceptor list.
     *
     * The list is embedded as an argument of the `InterceptorInjector::for*()` call inside a
     * generated proxy method body, so every line after the opening bracket sits three levels
     * deep: method body (2 levels) plus the injector call arguments (1 level), 4 spaces each.
     */
    private const string JOINPOINT_ARGUMENT_INDENT = '            ';

    /**
     * @var list<GeneratedInterceptor>
     */
    private readonly array $interceptors;

    /**
     * @param array<GeneratedInterceptor|string> $interceptors Only generated interceptor descriptors are
     *                                                         accepted, string entries are rejected loudly
     */
    public function __construct(array $interceptors)
    {
        $descriptors = [];
        foreach ($interceptors as $interceptor) {
            if (!$interceptor instanceof GeneratedInterceptor) {
                throw new AspectException(
                    'Interceptor list expects generated interceptor descriptors, got ' . get_debug_type($interceptor),
                );
            }
            $descriptors[] = $interceptor;
        }
        $this->interceptors = $descriptors;
    }

    public function generate(string $indent = self::JOINPOINT_ARGUMENT_INDENT): string
    {
        if ($this->interceptors === []) {
            return '[]';
        }

        $printed = (new GeneratedCodePrinter(['shortArraySyntax' => true]))->prettyPrintExpr($this->getNode());

        return str_replace("\n", "\n" . $indent, $printed);
    }

    public function getNode(): Array_
    {
        return new Array_(array_map(
            static fn(GeneratedInterceptor $interceptor): ArrayItem => new ArrayItem(self::createCallNode($interceptor)),
            $this->interceptors,
        ), ['kind' => Array_::KIND_SHORT]);
    }

    private static function createCallNode(GeneratedInterceptor $interceptor): StaticCall
    {
        $args = [
            new Arg(self::createAdviceAccessorNode($interceptor)),
        ];

        if ($interceptor->order !== 0) {
            $args[] = new Arg(new Int_($interceptor->order), name: new Identifier('order'));
        }

        return new StaticCall(new FullyQualified(Interceptor::class), $interceptor->factoryMethod, $args);
    }

    private static function createAdviceAccessorNode(GeneratedInterceptor $interceptor): Expr
    {
        if ($interceptor->usesContainerAdvice) {
            return new StaticCall(
                new FullyQualified(The::class),
                'advice',
                [
                    new Arg(new String_($interceptor->advisorId)),
                ],
            );
        }

        if ($interceptor->aspectClass === null || $interceptor->adviceMethod === null) {
            throw new \LogicException('Aspect-backed interceptor descriptor is incomplete');
        }

        // The eager first-class-callable form is deliberate for generated proxies: this code
        // only runs when the intercepted method/hook is already executing, so the interceptor
        // is needed right now and a lazy-proxy detour would be pure overhead. Only advisor
        // cache files use the lazy static-data form of the Interceptor facade.
        return new MethodCall(
            new StaticCall(new FullyQualified(The::class), 'aspect', [
                new Arg(new ClassConstFetch(new FullyQualified($interceptor->aspectClass), 'class')),
            ]),
            $interceptor->adviceMethod,
            [new VariadicPlaceholder()],
        );
    }
}
