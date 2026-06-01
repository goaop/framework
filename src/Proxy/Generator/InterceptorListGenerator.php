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

use Go\Aop\Framework\GeneratedInterceptor;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\VariadicPlaceholder;

/**
 * Renders generated interceptor descriptors as Interceptor::* factory calls.
 *
 * @internal
 */
final class InterceptorListGenerator
{
    /**
     * @param list<GeneratedInterceptor|string> $interceptors
     */
    public function __construct(private readonly array $interceptors) {}

    /**
     * @param list<GeneratedInterceptor|string> $interceptors
     * @return list<string>
     */
    public static function aspectClasses(array $interceptors): array
    {
        $classes = [];
        foreach ($interceptors as $interceptor) {
            $interceptor = self::normalize($interceptor);
            $classes[$interceptor->aspectClass] = $interceptor->aspectClass;
        }

        return array_values($classes);
    }

    public function generate(string $indent): string
    {
        if ($this->interceptors === []) {
            return '[]';
        }

        $lines = ['['];
        foreach ($this->normalizedInterceptors() as $interceptor) {
            $lines[] = $indent . '    Interceptor::' . $interceptor->factoryMethod . '(';
            $lines[] = $indent . '        The::aspect(' . self::shortClassName($interceptor->aspectClass) . '::class)->' . $interceptor->adviceMethod . '(...),';
            if ($interceptor->order !== 0) {
                $lines[] = $indent . '        order: ' . $interceptor->order . ',';
            }
            $lines[] = $indent . '    ),';
        }
        $lines[] = $indent . ']';

        return implode("\n", $lines);
    }

    public function getNode(): Array_
    {
        return new Array_(array_map(
            static fn(GeneratedInterceptor $interceptor): ArrayItem => new ArrayItem(self::createCallNode($interceptor)),
            $this->normalizedInterceptors()
        ), ['kind' => Array_::KIND_SHORT]);
    }

    /**
     * @return list<GeneratedInterceptor>
     */
    private function normalizedInterceptors(): array
    {
        return array_map(self::normalize(...), $this->interceptors);
    }

    private static function normalize(GeneratedInterceptor|string $interceptor): GeneratedInterceptor
    {
        if (is_string($interceptor)) {
            return GeneratedInterceptor::fromAdvisorId($interceptor);
        }

        return $interceptor;
    }

    private static function createCallNode(GeneratedInterceptor $interceptor): StaticCall
    {
        $args = [
            new Arg(new MethodCall(
                new StaticCall(new Name('The'), 'aspect', [
                    new Arg(new ClassConstFetch(new Name(self::shortClassName($interceptor->aspectClass)), 'class')),
                ]),
                $interceptor->adviceMethod,
                [new VariadicPlaceholder()]
            )),
        ];

        if ($interceptor->order !== 0) {
            $args[] = new Arg(new \PhpParser\Node\Scalar\Int_($interceptor->order), name: new Identifier('order'));
        }

        return new StaticCall(new Name('Interceptor'), $interceptor->factoryMethod, $args);
    }

    private static function shortClassName(string $className): string
    {
        $lastSeparator = strrpos($className, '\\');

        return $lastSeparator === false ? $className : substr($className, $lastSeparator + 1);
    }
}
