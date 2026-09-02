<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\CompilableToPhp;
use Go\Aop\Pointcut;
use Go\ParserReflection\ReflectionFileNamespace;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

use function in_array;

/**
 * Inheritance pointcut that matches any child for given parent or implements given interface
 */
final readonly class ClassInheritancePointcut implements Pointcut, CompilableToPhp
{
    /**
     * Inheritance class matcher constructor
     * @param string $parentClassOrInterfaceName Parent class or interface name to match in hierarchy
     */
    public function __construct(private string $parentClassOrInterfaceName) {}

    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null,
    ): bool {
        // We match only with ReflectionClass as a context
        if (!$context instanceof ReflectionClass) {
            return false;
        }

        // Otherwise, we match only if given context is child of given previously class name (either interface or class)
        return $context->isSubclassOf($this->parentClassOrInterfaceName) || in_array($this->parentClassOrInterfaceName, (array) $context->getInterfaceNames());
    }

    public function getKind(): int
    {
        return self::KIND_CLASS;
    }

    public function compileToPhp(): Expr
    {
        // The parent name comes from existing code, so a ::class fetch is always safe
        return new New_(new FullyQualified(self::class), [
            new Arg(new ClassConstFetch(new FullyQualified($this->parentClassOrInterfaceName), 'class')),
        ]);
    }
}
