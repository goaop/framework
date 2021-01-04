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

use Laminas\Code\Generator\AbstractGenerator;
use ReflectionFunctionAbstract;

/**
 * Prepares the function call argument list
 */
final class FunctionCallArgumentListGenerator extends AbstractGenerator
{
    /**
     * List of function arguments
     *
     * @var string[]
     */
    private array $arguments = [];

    /**
     * If function contains optional arguments
     */
    private bool $hasOptionals = false;

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
        parent::__construct();

        foreach ($functionLike->getParameters() as $parameter) {
            $byReference  = ($parameter->isPassedByReference() && !$parameter->isVariadic()) ? '&' : '';
            $this->hasOptionals = $this->hasOptionals || $parameter->isOptional();
            $this->arguments[]  = $byReference . '$' . $parameter->name;
        }
        if ($functionLike->isVariadic()) {
            // Variadic argument is last and should be handled separately
            $this->variadicArgument = array_pop($this->arguments);
        }
    }

    /**
     * @inheritDoc
     */
    public function generate(): string
    {
        $argumentsPart = [];
        if ($this->variadicArgument !== null) {
            $argumentsPart[] = $this->variadicArgument;
        }
        if (!empty($this->arguments)) {
            $argumentLine = '[' . implode(', ', $this->arguments) . ']';
            if ($this->hasOptionals) {
                $argumentLine = "\\array_slice($argumentLine, 0, \\func_num_args())";
            }
            array_unshift($argumentsPart, $argumentLine);
        }

        return implode(', ', $argumentsPart);
    }
}
