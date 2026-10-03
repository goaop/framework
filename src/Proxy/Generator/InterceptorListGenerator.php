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
use Go\Aop\Exception\WeavingException;
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
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;

/**
 * Renders generated interceptor descriptors as Interceptor::* factory calls.
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
     * @param ProxyImports|null                  $imports      Imports of the generated file; short class names
     *                                                         are emitted when omitted
     */
    public function __construct(array $interceptors, private readonly ?ProxyImports $imports = null)
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

    /**
     * @param list<GeneratedInterceptor> $interceptors
     * @return list<string>
     */
    public static function aspectClasses(array $interceptors): array
    {
        $classes = [];
        foreach ($interceptors as $interceptor) {
            if ($interceptor->aspectClass === null) {
                continue;
            }
            $classes[$interceptor->aspectClass] = $interceptor->aspectClass;
        }

        return array_values($classes);
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
            fn(GeneratedInterceptor $interceptor): ArrayItem => new ArrayItem($this->createCallNode($interceptor)),
            $this->interceptors,
        ), ['kind' => Array_::KIND_SHORT]);
    }

    private function createCallNode(GeneratedInterceptor $interceptor): StaticCall
    {
        $args = [
            new Arg($this->createAdviceAccessorNode($interceptor)),
        ];

        if ($interceptor->order !== 0) {
            $args[] = new Arg(new Int_($interceptor->order), name: new Identifier('order'));
        }

        return new StaticCall($this->className(Interceptor::class), $interceptor->factoryMethod, $args);
    }

    private function createAdviceAccessorNode(GeneratedInterceptor $interceptor): Expr
    {
        if ($interceptor->usesContainerAdvice) {
            return new StaticCall(
                $this->className(The::class),
                'advice',
                [
                    new Arg(new String_($interceptor->advisorId)),
                ],
            );
        }

        if ($interceptor->aspectClass === null || $interceptor->adviceMethod === null) {
            throw new WeavingException('Aspect-backed interceptor descriptor is incomplete');
        }

        // The eager first-class-callable form is deliberate for generated proxies: this code
        // only runs when the intercepted method/hook is already executing, so the interceptor
        // is needed right now and a lazy-proxy detour would be pure overhead. Only advisor
        // cache files use the lazy static-data form of the Interceptor facade.
        return new MethodCall(
            new StaticCall($this->className(The::class), 'aspect', [
                new Arg(new ClassConstFetch($this->className($interceptor->aspectClass), 'class')),
            ]),
            $interceptor->adviceMethod,
            [new VariadicPlaceholder()],
        );
    }

    /**
     * Returns the name node generated code uses for the class: its alias in the file imports
     */
    private function className(string $className): Name
    {
        return new Name($this->imports?->import($className) ?? self::shortClassName($className));
    }

    private static function shortClassName(string $className): string
    {
        $lastSeparator = strrpos($className, '\\');

        return $lastSeparator === false ? $className : substr($className, $lastSeparator + 1);
    }
}
