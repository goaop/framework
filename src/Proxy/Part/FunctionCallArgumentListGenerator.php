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

use ReflectionFunctionAbstract;

/**
 * Prepares the function call argument list
 *
 * Optional arguments that were not passed must not be passed to the original function either, so its
 * func_num_args() and func_get_args() stay the same. The number of passed arguments is stored in a local variable
 * and compared with the number of arguments: the usual call passing all of them gets a plain array, only a call
 * that omits optional arguments cuts the list with array_slice().
 */
final class FunctionCallArgumentListGenerator
{
    /**
     * List of function arguments
     *
     * @var list<string>
     */
    private array $arguments = [];

    /**
     * Number of leading arguments that are always passed
     */
    private int $requiredCount = 0;

    /**
     * Definition of variadic argument or null if function is not variadic
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
            $byReference       = ($parameter->isPassedByReference() && !$parameter->isVariadic()) ? '&' : '';
            $this->arguments[] = $byReference . '$' . $parameter->name;
            if (!$parameter->isOptional()) {
                // Required parameters precede the optional ones
                $this->requiredCount++;
            }
        }
        if ($functionLike->isVariadic()) {
            // Variadic argument is last and should be handled separately
            $this->variadicArgument = array_pop($this->arguments);
        }
    }

    public function generate(): string
    {
        $argumentsPart = [];
        if ($this->variadicArgument !== null) {
            $argumentsPart[] = $this->variadicArgument;
        }
        if (!empty($this->arguments)) {
            array_unshift($argumentsPart, $this->generateArgumentList());
        }

        return implode(', ', $argumentsPart);
    }

    /**
     * Generates the list of the passed non-variadic arguments
     */
    private function generateArgumentList(): string
    {
        $argumentCount = count($this->arguments);
        $allArguments  = '[' . implode(', ', $this->arguments) . ']';
        if ($this->requiredCount === $argumentCount) {
            // Every argument is always passed
            return $allArguments;
        }
        // The local $__argsCount keeps the number of passed arguments for array_slice()
        return "(\$__argsCount = \\func_num_args()) >= $argumentCount"
            . " ? $allArguments"
            . " : \\array_slice($allArguments, 0, \$__argsCount)";
    }
}
