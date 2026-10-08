<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Part;

use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\Int_;
use ReflectionFunctionAbstract;

/**
 * Prepares the function call argument list
 */
final class FunctionCallArgumentListGenerator
{
    /**
     * List of function arguments: parameter name => passed by reference
     *
     * @var array<string, bool>
     */
    private array $arguments = [];

    /**
     * If function contains optional arguments
     */
    private bool $hasOptionals = false;

    /**
     * Name of the variadic parameter or null if function is not variadic
     */
    private ?string $variadicArgument = null;

    /**
     * FunctionCallArgumentList constructor.
     *
     * @param ReflectionFunctionAbstract $functionLike Instance of function or method to call
     */
    public function __construct(ReflectionFunctionAbstract $functionLike)
    {
        foreach ($functionLike->getParameters() as $parameter) {
            $this->hasOptionals = $this->hasOptionals || $parameter->isOptional();
            $this->arguments[$parameter->name] = $parameter->isPassedByReference() && !$parameter->isVariadic();
        }
        if ($functionLike->isVariadic()) {
            // Variadic argument is last and should be handled separately
            $this->variadicArgument = array_key_last($this->arguments);
            array_pop($this->arguments);
        }
    }

    public function generate(): string
    {
        $argumentsPart = [];
        if ($this->variadicArgument !== null) {
            $argumentsPart[] = '$' . $this->variadicArgument;
        }
        if (!empty($this->arguments)) {
            $arguments = [];
            foreach ($this->arguments as $name => $byReference) {
                $arguments[] = ($byReference ? '&$' : '$') . $name;
            }
            $argumentLine = '[' . implode(', ', $arguments) . ']';
            if ($this->hasOptionals) {
                $argumentLine = "\\array_slice($argumentLine, 0, \\func_num_args())";
            }
            array_unshift($argumentsPart, $argumentLine);
        }

        return implode(', ', $argumentsPart);
    }

    /**
     * Returns the argument list of {@see generate()} as AST call arguments
     *
     * @return list<Arg>
     */
    public function getArgs(): array
    {
        $args = [];
        if ($this->arguments !== []) {
            $items = [];
            foreach ($this->arguments as $name => $byReference) {
                $items[] = new ArrayItem(new Variable($name), null, $byReference);
            }
            $argumentList = new Array_($items, ['kind' => Array_::KIND_SHORT]);
            if ($this->hasOptionals) {
                $argumentList = new FuncCall(new FullyQualified('array_slice'), [
                    new Arg($argumentList),
                    new Arg(new Int_(0)),
                    new Arg(new FuncCall(new FullyQualified('func_num_args'))),
                ]);
            }
            $args[] = new Arg($argumentList);
        }
        if ($this->variadicArgument !== null) {
            $args[] = new Arg(new Variable($this->variadicArgument));
        }

        return $args;
    }
}
