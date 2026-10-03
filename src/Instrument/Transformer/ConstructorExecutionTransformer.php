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

namespace Go\Instrument\Transformer;

use Go\Aop\Exception\WeavingException;
use Go\Aop\Framework\ReflectionConstructorInvocation;
use Go\Aop\InitializationAware;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\EnumCase;
use WeakReference;

/**
 * Transforms the source code to add an ability to intercept new instances creation
 *
 * @see https://github.com/php/php-src/blob/master/Zend/zend_language_parser.y
 *
 */
final class ConstructorExecutionTransformer implements NodeRewriter
{
    /**
     * List of constructor invocations per class
     *
     * @var array<string, ReflectionConstructorInvocation<object>>
     */
    private static array $constructorInvocationsCache = [];

    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Singletone
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Forgets the singleton and the constructor invocations created for the intercepted classes
     *
     * @internal For tests and processes that boot the framework again
     */
    public static function reset(): void
    {
        self::$instance                    = null;
        self::$constructorInvocationsCache = [];
    }

    public function getNodeTypes(): array
    {
        return [New_::class];
    }

    /**
     * Rewrites the "new" expression with our implementation
     */
    public function rewriteNode(Node $node, StreamMetaData $file): bool
    {
        // Anonymous classes (`new class {...}`) have no name to construct through the interceptor
        if (!$node instanceof New_ || $node->class instanceof Class_ || self::isInConstantExpression($node)) {
            return false;
        }
        $startPosition   = $node->getAttribute('startTokenPos');
        $endClassNamePos = $node->class->getAttribute('endTokenPos');
        if (!is_int($startPosition) || !is_int($endClassNamePos)) {
            return false;
        }

        $isExplicitClass = $node->class instanceof Name;
        $file->tokenStream[$startPosition]->text = '\\' . self::class . '::getInstance()->{';
        if ($file->tokenStream[$startPosition + 1]->id === T_WHITESPACE) {
            unset($file->tokenStream[$startPosition + 1]);
        }
        $expressionSuffix                           = $isExplicitClass ? '::class}' : '}';
        $file->tokenStream[$endClassNamePos]->text .= $expressionSuffix;

        return true;
    }

    /**
     * Checks if the `new` expression is inside a constant-expression context
     *
     * Since PHP 8.1 `new` may appear inside constant-expression contexts: parameter default
     * values, static variable initializers, attribute arguments and global constants (and
     * php-parser also accepts it in property/class-constant defaults and enum case values).
     * Such occurrences must stay untouched — the interceptor rewrite
     * `...getInstance()->{Foo::class}(...)` is not a valid constant expression and would
     * trigger a compile-time fatal error (https://github.com/goaop/framework/issues/603).
     *
     * Property hooks on promoted parameters contain runtime code, so for the containers other
     * than attributes only the initializer child expression is a constant expression.
     */
    private static function isInConstantExpression(New_ $newExpression): bool
    {
        $child  = $newExpression;
        $parent = self::getParent($child);
        while ($parent !== null) {
            $constExpr = match (true) {
                $parent instanceof Attribute    => $child,
                $parent instanceof Param        => $parent->default,
                $parent instanceof StaticVar    => $parent->default,
                $parent instanceof PropertyItem => $parent->default,
                $parent instanceof Const_       => $parent->value,
                $parent instanceof EnumCase     => $parent->expr,
                default                         => null,
            };
            if ($constExpr === $child) {
                return true;
            }
            $child  = $parent;
            $parent = self::getParent($child);
        }

        return false;
    }

    /**
     * Returns the parent node connected by {@see SyntaxTreeRewriter}
     */
    private static function getParent(Node $node): ?Node
    {
        $parentReference = $node->getAttribute('weak_parent');
        $parent          = $parentReference instanceof WeakReference ? $parentReference->get() : null;

        return $parent instanceof Node ? $parent : null;
    }

    /**
     * Magic interceptor for instance creation
     *
     * @param string $className Name of the class to construct
     */
    public function __get(string $className): object
    {
        return static::construct($className);
    }

    /**
     * Magic interceptor for instance creation
     *
     * @param string  $className Name of the class to construct
     * @param list<mixed> $args  Arguments for the constructor
     */
    public function __call(string $className, array $args): object
    {
        return static::construct($className, $args);
    }

    /**
     * Default implementation for accessing joinpoint or creating a new one on-fly
     *
     * @param list<mixed> $arguments
     */
    protected static function construct(string $fullClassName, array $arguments = []): object
    {
        $fullClassName = ltrim($fullClassName, '\\');
        if (is_subclass_of($fullClassName, InitializationAware::class)) {
            /** @var class-string<InitializationAware<object>> $fullClassName */
            return $fullClassName::__initialization($arguments);
        }

        $invocation = self::$constructorInvocationsCache[$fullClassName] ?? null;
        if ($invocation === null) {
            // A missing class is not cached: it may still become loadable later in the request
            if (!class_exists($fullClassName)) {
                throw new WeavingException("Cannot instantiate non-existent class: {$fullClassName}");
            }
            $invocation = self::$constructorInvocationsCache[$fullClassName] = new ReflectionConstructorInvocation([], $fullClassName);
        }

        return $invocation->__invoke($arguments);
    }
}
