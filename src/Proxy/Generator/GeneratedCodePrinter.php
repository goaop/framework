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

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\PrettyPrinter\Standard;

/**
 * Pretty-printer for generated proxy code.
 *
 * Keeps joinpoint initialization readable even when method bodies are parsed
 * through AST nodes before class generation.
 */
class GeneratedCodePrinter extends Standard
{
    /**
     * Factory methods of InterceptorInjector: their calls start a joinpoint initialization
     */
    private const array INJECTOR_METHODS = [
        'forMethod', 'forStaticMethod', 'forProperty', 'forFunction', 'forStaticInitialization', 'forInitialization',
    ];

    /**
     * Factory methods of the Interceptor facade: their calls form an interceptor list
     */
    private const array INTERCEPTOR_METHODS = ['before', 'after', 'around', 'afterThrowing'];

    #[\Override]
    protected function pExpr_Array(Expr\Array_ $node): string
    {
        if (empty($node->items)) {
            return $node->getAttribute('kind') === Expr\Array_::KIND_SHORT ? '[]' : 'array()';
        }
        if (!$this->isInterceptorArray($node)) {
            return parent::pExpr_Array($node);
        }

        $isShort = $node->getAttribute('kind') === Expr\Array_::KIND_SHORT;

        return ($isShort ? '[' : 'array(')
            . $this->pCommaSeparatedMultiline($node->items, true)
            . $this->nl
            . ($isShort ? ']' : ')');
    }

    #[\Override]
    protected function pExpr_StaticCall(Expr\StaticCall $node): string
    {
        // The class may be imported under any alias (see ProxyImports), so calls are also recognized by method name
        if ($node->class instanceof Name && $this->isCallOf($node, 'InterceptorInjector', self::INJECTOR_METHODS)) {
            $name = $node->name instanceof Identifier ? $node->name->toString() : $this->p($node->name);

            return $this->pStaticDereferenceLhs($node->class) . '::' . $name
                . '(' . $this->pCommaSeparatedMultiline($node->args, true) . $this->nl . ')';
        }

        return parent::pExpr_StaticCall($node);
    }

    private function isInterceptorArray(Expr\Array_ $node): bool
    {
        foreach ($node->items as $item) {
            if (!$item->value instanceof Expr\StaticCall) {
                return false;
            }
            if (!$this->isCallOf($item->value, 'Interceptor', self::INTERCEPTOR_METHODS)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $methodNames
     */
    private function isCallOf(Expr\StaticCall $call, string $classShortName, array $methodNames): bool
    {
        if (!$call->class instanceof Name) {
            return false;
        }

        return str_ends_with($call->class->toString(), $classShortName)
            || ($call->name instanceof Identifier && in_array($call->name->toString(), $methodNames, true));
    }
}
