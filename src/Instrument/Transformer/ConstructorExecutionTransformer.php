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
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitorAbstract;
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
        $metadata->refreshSyntaxTreeFromTokenStream();
        $cloningTraverser = new NodeTraverser();
        $cloningTraverser->addVisitor(new CloningVisitor());
        $newSyntaxTree = $cloningTraverser->traverse($metadata->syntaxTree);

        $visitor = new class extends NodeVisitorAbstract {
            public bool $hasChanges = false;

            public function leaveNode(Node $node): ?Node
            {
                if (!$node instanceof New_) {
                    return null;
                }

                $this->hasChanges = true;
                $classReference   = $node->class instanceof Name
                    ? new ClassConstFetch($node->class, 'class')
                    : $node->class;
                $transformerInstance = new Node\Expr\StaticCall(
                    new FullyQualified(ConstructorExecutionTransformer::class),
                    'getInstance'
                );

                if ($node->args === []) {
                    return new PropertyFetch($transformerInstance, $classReference);
                }

                return new MethodCall($transformerInstance, $classReference, $node->args);
            }
        };

        $rewritingTraverser = new NodeTraverser();
        $rewritingTraverser->addVisitor($visitor);
        $newSyntaxTree = $rewritingTraverser->traverse($newSyntaxTree);

        if (!$visitor->hasChanges) {
            return TransformerResultEnum::RESULT_ABSTAIN;
        }

        $metadata->applySyntaxTree($newSyntaxTree);

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
            $dynamicInit = AspectContainer::INIT_PREFIX . ':root';
            if (class_exists($fullClassName)) {
                try {
                    $joinPointsRef = new ReflectionProperty($fullClassName, '__joinPoints');
                    $joinPoints = $joinPointsRef->getValue();
                    if (is_array($joinPoints) && isset($joinPoints[$dynamicInit])) {
                        $jp = $joinPoints[$dynamicInit];
                        if ($jp instanceof ReflectionConstructorInvocation) {
                            $invocation = $jp;
                        }
                    }
                } catch (ReflectionException $e) {
                    $invocation = null;
                }
                if (!$invocation) {
                    $invocation = new ReflectionConstructorInvocation([], $fullClassName);
                }
            }
            self::$constructorInvocationsCache[$fullClassName] = $invocation;
        }

        $cachedInvocation = self::$constructorInvocationsCache[$fullClassName];
        if ($cachedInvocation === null) {
            throw new \LogicException("Cannot instantiate non-existent class: {$fullClassName}");
        }

        $result = $cachedInvocation->__invoke($arguments);
        if (!is_object($result)) {
            throw new \LogicException('Constructor invocation did not return an object');
        }

        return $result;
    }
}
