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

use Go\Aop\Framework\ReflectionConstructorInvocation;
use Go\Aop\InitializationAware;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\FindingVisitor;

/**
 * Transforms the source code to add an ability to intercept new instances creation
 *
 * @see https://github.com/php/php-src/blob/master/Zend/zend_language_parser.y
 *
 */
final class ConstructorExecutionTransformer implements SourceTransformer
{
    /**
     * List of constructor invocations per class
     *
     * @var array<string, ReflectionConstructorInvocation<object>|null>
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
     * Rewrites all "new" expressions with our implementation
     */
    public function transform(StreamMetaData $metadata): TransformerResultEnum
    {
        $newExpressionFinder = new FindingVisitor(fn(Node $node) => $node instanceof New_);

        // TODO: move this logic into walkSyntaxTree(Visitor $nodeVistor) method
        $traverser = new NodeTraverser();
        $traverser->addVisitor($newExpressionFinder);
        $traverser->traverse($metadata->syntaxTree);

        /** @var Node\Expr\New_[] $newExpressions */
        $newExpressions = $newExpressionFinder->getFoundNodes();

        if (empty($newExpressions)) {
            return TransformerResultEnum::RESULT_ABSTAIN;
        }

        foreach ($newExpressions as $newExpressionNode) {
            $startPosition = $newExpressionNode->getAttribute('startTokenPos');
            $endClassNamePos = $newExpressionNode->class->getAttribute('endTokenPos');
            if (!is_int($startPosition) || !is_int($endClassNamePos)) {
                continue;
            }

            $isExplicitClass = $newExpressionNode->class instanceof Name;
            $metadata->tokenStream[$startPosition]->text = '\\' . self::class . '::getInstance()->{';
            if ($metadata->tokenStream[$startPosition + 1]->id === T_WHITESPACE) {
                unset($metadata->tokenStream[$startPosition + 1]);
            }
            $expressionSuffix                           = $isExplicitClass ? '::class}' : '}';
            $metadata->tokenStream[$endClassNamePos]->text .= $expressionSuffix;
        }

        return TransformerResultEnum::RESULT_TRANSFORMED;
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
        if (!isset(self::$constructorInvocationsCache[$fullClassName])) {
            $invocation  = null;
            if (class_exists($fullClassName)) {
                if (!is_subclass_of($fullClassName, InitializationAware::class)) {
                    $invocation = new ReflectionConstructorInvocation([], $fullClassName);
                }
            }
            self::$constructorInvocationsCache[$fullClassName] = $invocation;
        }

        if (is_subclass_of($fullClassName, InitializationAware::class)) {
            /** @var class-string<InitializationAware<object>> $fullClassName */
            return $fullClassName::__aop__initialization($arguments);
        }

        $cachedInvocation = self::$constructorInvocationsCache[$fullClassName];
        if ($cachedInvocation === null) {
            throw new \LogicException("Cannot instantiate non-existent class: {$fullClassName}");
        }

        return $cachedInvocation->__invoke($arguments);
    }
}
