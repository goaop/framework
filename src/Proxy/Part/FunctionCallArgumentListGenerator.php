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
 * func_num_args() and func_get_args() stay the same. The list is chosen by the number of passed arguments with a
 * match of array literals, which costs no more than a plain array; beyond MAX_MATCHED_OPTIONALS optional arguments
 * the list is cut with array_slice() instead, to keep the generated code short.
 */
final class FunctionCallArgumentListGenerator
{
    /**
     * Largest number of optional arguments whose argument lists are chosen with a match
     */
    private const int MAX_MATCHED_OPTIONALS = 4;

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
        $optionalCount = $argumentCount - $this->requiredCount;
        if ($optionalCount === 0) {
            return $allArguments;
        }
        if ($optionalCount > self::MAX_MATCHED_OPTIONALS) {
            return "\\array_slice($allArguments, 0, \\func_num_args())";
        }

        $arms = [];
        for ($passedCount = $this->requiredCount; $passedCount < $argumentCount; $passedCount++) {
            $arms[] = $passedCount . ' => [' . implode(', ', array_slice($this->arguments, 0, $passedCount)) . ']';
        }
        // All arguments passed, and more of them for a variadic function
        $arms[] = 'default => ' . $allArguments;

        return 'match (\\func_num_args()) { ' . implode(', ', $arms) . ' }';
    }
}
