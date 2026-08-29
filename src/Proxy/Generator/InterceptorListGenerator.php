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
                    'Interceptor list expects generated interceptor descriptors, got ' . get_debug_type($interceptor)
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

    public function generate(string $indent): string
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
            $this->interceptors
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

        return new StaticCall(new Name('Interceptor'), $interceptor->factoryMethod, $args);
    }

    private static function createAdviceAccessorNode(GeneratedInterceptor $interceptor): Expr
    {
        if ($interceptor->usesContainerAdvice) {
            return new StaticCall(
                new Name('The'),
                'advice',
                [
                    new Arg(new String_($interceptor->advisorId)),
                ]
            );
        }

        if ($interceptor->aspectClass === null || $interceptor->adviceMethod === null) {
            throw new \LogicException('Aspect-backed interceptor descriptor is incomplete');
        }

        return new MethodCall(
            new StaticCall(new Name('The'), 'aspect', [
                new Arg(new ClassConstFetch(new Name(self::shortClassName($interceptor->aspectClass)), 'class')),
            ]),
            $interceptor->adviceMethod,
            [new VariadicPlaceholder()]
        );
    }

    private static function shortClassName(string $className): string
    {
        $lastSeparator = strrpos($className, '\\');

        return $lastSeparator === false ? $className : substr($className, $lastSeparator + 1);
    }
}
