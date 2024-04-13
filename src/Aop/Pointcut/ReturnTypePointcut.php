<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Return type filter matcher methods and function with specific return type
 *
 * Type name can contain wildcards '*', '**' and '?'
 *
 * This implementation currently doesn't support properly matching of complex types,
 * thus union/intersection/DNF types are not supported yet here.
 */
final readonly class ReturnTypePointcut implements Pointcut
{
    /**
     * Return type name to match, can contain wildcards *,?
     */
    private string $typeName;

    /**
     * Pattern for regular expression matching
     */
    private string $regexp;

    /**
     * Return type name matcher constructor accepts name or glob pattern of the type to match
     *
     * @param (string&non-empty-string) $returnTypeName
     */
    public function __construct(string $returnTypeName)
    {
        $returnTypeName = trim($returnTypeName, '\\');
        if (strlen($returnTypeName) === 0) {
            throw new \InvalidArgumentException("Return type name must not be empty");
        }
        $this->typeName = $returnTypeName;
        $this->regexp   = '/^(' . strtr(preg_quote($this->typeName, '/'), [
            '\\*'    => '[^\\\\]+',
            '\\?'    => '.',
        ]) . ')$/';
    }

    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction $reflector = null,
        object|string                                          $instanceOrScope = null,
        array                                                  $arguments = null
    ): bool {
        // With only static context we always match, as we don't have any information about concrete reflector
        if (!isset($reflector)) {
            return true;
        }

        // We don't support anything that is not function-like
        if (!$reflector instanceof ReflectionFunctionAbstract) {
            return false;
        }

        // If reflector doesn't have a return type, we should not match
        if (!$reflector->hasReturnType()) {
            return false;
        }

        $returnType = (string) $reflector->getReturnType();

        // Either we have exact type string match or type matches our regular expression
        return ($returnType === $this->typeName) || preg_match($this->regexp, $returnType);
    }

    public function getKind(): int
    {
        return Pointcut::KIND_METHOD | Pointcut::KIND_FUNCTION;
    }
}
