<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Magic method pointcut is a dynamic checker that verifies calls for __call and __callStatic
 *
 * With one (or two) arguments it always statically matches with __call and __callStatic methods.
 * With four arguments, it takes real argument for invocation and matches it again dynamically.
 */
final readonly class MagicMethodDynamicPointcut implements Pointcut
{
    /**
     * Compiled regular expression for matching
     */
    private string $regexp;

    /**
     * Magic method matcher constructor
     *
     * @param string $methodName Method name to match, can contain wildcards "*","?" or "|"
     */
    public function __construct(private string $methodName) {
        $this->regexp = '/^(' . strtr(
            preg_quote($this->methodName, '/'),
            [
                '\\*' => '.*?',
                '\\?' => '.',
                '\\|' => '|'
            ]
        ) . ')$/';
    }

    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction $reflector = null,
        object|string                                          $instanceOrScope = null,
        array                                                  $arguments = null
    ): bool {
        // Magic methods can be only inside class context
        if (!$context instanceof ReflectionClass) {
            return false;
        }

        // For pre-filter we match only with context that has magic methods
        if (!isset($reflector)) {
            return $context->hasMethod('__call') || $context->hasMethod('__callStatic');
        }

        // If we receive something not expected here (ReflectionMethod), we should not match
        if (!$reflector instanceof ReflectionMethod) {
            return false;
        }

        // With single parameter (statically) always matches for __call, __callStatic methods
        if ($instanceOrScope === null) {
            return ($reflector->name === '__call' || $reflector->name === '__callStatic');
        }

        // If for some reason we don't have arguments, or first argument is not a string with valid function name
        if (!isset($arguments) || count($arguments) < 1 || !is_string($arguments[0])) {
            return false;
        }

        // for __call and __callStatic method name is the first argument on invocation
        [$methodName] = $arguments;

        // Perform final dynamic check
        return ($methodName === $this->methodName) || preg_match($this->regexp, $methodName);
    }

    public function getKind(): int
    {
        return Pointcut::KIND_METHOD | Pointcut::KIND_DYNAMIC;
    }
}
