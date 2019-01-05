<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor that resolves class name for `self` nodes with FQN
 */
final class SelfValueVisitor extends NodeVisitorAbstract
{
    /**
     * List of replaced nodes
     *
     * @var Node[]
     */
    protected $replacedNodes = [];

    /**
     * Current namespace
     *
     * @var null|Name|string
     */
    protected $namespace;

    /**
     * Current class name
     *
     * @var null|Name
     */
    protected $className;

    /**
     * Returns list of changed `self` nodes
     *
     * @return Node[]
     */
    public function getReplacedNodes()
    {
        return $this->replacedNodes;
    }

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes)
    {
        $this->namespace     = null;
        $this->className     = null;
        $this->replacedNodes = [];
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->namespace = $node->name->toString();
        } elseif ($node instanceof Stmt\Class_) {
            if ($node->name !== null) {
                $this->className = new Name($node->name->toString());
            }
        } elseif ($node instanceof Stmt\ClassMethod || $node instanceof Expr\Closure) {
            $node->returnType = $this->resolveType($node->returnType);
        } elseif ($node instanceof Node\Param) {
            $node->type = $this->resolveType($node->type);
        } elseif (
            $node instanceof Expr\StaticCall
            || $node instanceof Expr\ClassConstFetch
            || $node instanceof Expr\New_
            || $node instanceof Expr\Instanceof_
        ) {
            if ($node->class instanceof Name) {
                $node->class = $this->resolveClassName($node->class);
            }
        } elseif ($node instanceof Stmt\Catch_) {
            foreach ($node->types as &$type) {
                $type = $this->resolveClassName($type);
            }
        }
    }

    /**
     * Resolves `self` class name with value
     *
     * @param Name $name Instance of original node
     *
     * @return Name|FullyQualified
     */
    protected function resolveClassName(Name $name)
    {
        // Skip all names except special `self`
        if (strtolower($name->toString()) !== 'self') {
            return $name;
        }

        // Save the original name
        $originalName = $name;
        $name = clone $originalName;
        $name->setAttribute('originalName', $originalName);

        $fullClassName    = Name::concat($this->namespace, $this->className);
        $resolvedSelfName = new FullyQualified('\\' . ltrim($fullClassName->toString(), '\\'), $name->getAttributes());

        $this->replacedNodes[] = $resolvedSelfName;

        return $resolvedSelfName;
    }

    /**
     * Helper method for resolving type nodes
     *
     * @param Node|string|null $node Instance of node
     *
     * @return Node|Name|FullyQualified
     */
    private function resolveType($node)
    {
        if ($node instanceof Node\NullableType) {
            $node->type = $this->resolveType($node->type);
            return $node;
        }
        if ($node instanceof Name) {
            return $this->resolveClassName($node);
        }

        return $node;
    }
}
