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
use Go\Core\AspectContainer;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use ReflectionException;
use ReflectionProperty;

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
     */
    private static array $constructorInvocationsCache = [];

    /**
     * Singletone
     */
    public static function getInstance(): self
    {
        static $instance;
        if ($instance === null) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Rewrites all "new" expressions with our implementation
     *
     * @return string See RESULT_XXX constants in the interface
     */
    public function transform(StreamMetaData $metadata): string
    {
        $newExpressionFinder = new NodeFinderVisitor([New_::class]);

        // TODO: move this logic into walkSyntaxTree(Visitor $nodeVistor) method
        $traverser = new NodeTraverser();
        $traverser->addVisitor($newExpressionFinder);
        $traverser->traverse($metadata->syntaxTree);

        /** @var Node\Expr\New_[] $newExpressions */
        $newExpressions = $newExpressionFinder->getFoundNodes();

        if (empty($newExpressions)) {
            return self::RESULT_ABSTAIN;
        }

        foreach ($newExpressions as $newExpressionNode) {
            $startPosition = $newExpressionNode->getAttribute('startTokenPos');

            $metadata->tokenStream[$startPosition]->text = '\\' . self::class . '::getInstance()->{';
            if ($metadata->tokenStream[$startPosition + 1]->id === T_WHITESPACE) {
                unset($metadata->tokenStream[$startPosition + 1]);
            }
            $isExplicitClass                            = $newExpressionNode->class instanceof Name;
            $endClassNamePos                            = $newExpressionNode->class->getAttribute('endTokenPos');
            $expressionSuffix                           = $isExplicitClass ? '::class}' : '}';
            $metadata->tokenStream[$endClassNamePos]->text .= $expressionSuffix;
        }

        return self::RESULT_TRANSFORMED;
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
     * @param string $className Name of the class to construct
     * @param array  $args      Arguments for the constructor
     */
    public function __call(string $className, array $args): object
    {
        return static::construct($className, $args);
    }

    /**
     * Default implementation for accessing joinpoint or creating a new one on-fly
     */
    protected static function construct(string $fullClassName, array $arguments = []): object
    {
        $fullClassName = ltrim($fullClassName, '\\');
        if (!isset(self::$constructorInvocationsCache[$fullClassName])) {
            $invocation  = null;
            $dynamicInit = AspectContainer::INIT_PREFIX . ':root';
            try {
                $joinPointsRef = new ReflectionProperty($fullClassName, '__joinPoints');
                $joinPoints = $joinPointsRef->getValue();
                if (isset($joinPoints[$dynamicInit])) {
                    $invocation = $joinPoints[$dynamicInit];
                }
            } catch (ReflectionException $e) {
                $invocation = null;
            }
            if (!$invocation) {
                $invocation = new ReflectionConstructorInvocation([], $fullClassName);
            }
            self::$constructorInvocationsCache[$fullClassName] = $invocation;
        }

        return self::$constructorInvocationsCache[$fullClassName]->__invoke($arguments);
    }
}
