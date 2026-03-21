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

namespace Go\Instrument\Transformer;

use PhpParser\Node;
<<<<<<< Updated upstream
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
=======
>>>>>>> Stashed changes
use PhpParser\Node\Name;
use PhpParser\Node\Param;
<<<<<<< Updated upstream
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\UnionType;
=======
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
>>>>>>> Stashed changes
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor that resolves class name for `self` nodes with FQN
 */
final class SelfValueVisitor extends NodeVisitorAbstract
{
    /**
     * @var Node[] List of replaced nodes
     */
    private array $replacedNodes = [];

    /**
     * Current namespace
     */
    private ?string $namespace = null;

    /**
     * Current class name
     */
    private ?string $className = null;

    /**
     * Returns list of changed `self` nodes
     *
     * @return Node[]
     */
    public function getReplacedNodes(): array
    {
        return $this->replacedNodes;
    }

    /**
     * @inheritDoc
     *
     * @return Name|null Covariance, either null for all non-relevant nodes or resolved FQN Name node for "self"
     */
    public function enterNode(Node $node): Name|null
    {
        if ($node instanceof Namespace_) {
<<<<<<< Updated upstream
            $this->namespace = !empty($node->name) ? $node->name->toString() : null;
        } elseif ($node instanceof ClassMethod || $node instanceof Closure) {
            if (isset($node->returnType)) {
                $node->returnType = $this->resolveType($node->returnType);
            }
        } elseif (($node instanceof Property) && (isset($node->type))) {
            $node->type = $this->resolveType($node->type);
        } elseif (($node instanceof Param) && (isset($node->type))) {
            $node->type = $this->resolveType($node->type);
        } elseif (
            $node instanceof StaticCall
            || $node instanceof ClassConstFetch
            || $node instanceof New_
            || $node instanceof Instanceof_
        ) {
            if ($node->class instanceof Name) {
                $node->class = $this->resolveClassName($node->class);
            }
        } elseif ($node instanceof Catch_) {
            foreach ($node->types as &$type) {
                $type = $this->resolveClassName($type);
            }
        } elseif ($node instanceof ClassLike) {
            if (! $node instanceof Trait_) {
                $this->className = !empty($node->name) ? new Name($node->name->toString()) : null;
            } else {
                $this->className = null;
            }
=======
            // There might be root namespace node, in this case namespace name will be null
            $this->namespace = $node->name?->toString();
        } elseif ($node instanceof Class_) {
            // For anonymous classes name will be null
            $this->className = $node->name?->toString();
        } elseif ($node instanceof Name && strtolower($node->name) === 'self') {
            return $this->resolveSelfClassName($node);
>>>>>>> Stashed changes
        }

        return null;
    }

    /**
     * Resolves `self` class name with value
     *
     * @param Name $nameNode Instance of original node
     */
    private function resolveSelfClassName(Name $nameNode): Name
    {
<<<<<<< Updated upstream
        // Skip all names except special `self`
        if (strtolower($name->toString()) !== 'self') {
            return $name;
        }

        if ($this->className === null) {
            return $name;
        }

        // Save the original name
        $originalName = $name;
        $name = clone $originalName;
        $name->setAttribute('originalName', $originalName);

=======
>>>>>>> Stashed changes
        $fullClassName    = Name::concat($this->namespace, $this->className);
        $resolvedSelfName = new Name('\\' . ltrim($fullClassName->toString(), '\\'), $nameNode->getAttributes());

        $this->replacedNodes[] = $resolvedSelfName;

        return $resolvedSelfName;
    }
<<<<<<< Updated upstream

    /**
     * Helper method for resolving type nodes
     *
     * @return NullableType|Name|FullyQualified|Identifier|UnionType|IntersectionType
     */
    private function resolveType(Node $node)
    {
        if ($node instanceof NullableType) {
            $node->type = $this->resolveType($node->type);
            return $node;
        }
        if ($node instanceof Name) {
            return $this->resolveClassName($node);
        }
        if ($node instanceof Identifier) {
            return $node;
        }

        if ($node instanceof UnionType || $node instanceof IntersectionType) {
            $types = [];
            foreach ($node->types as $type) {
                $types[] = $this->resolveType($type);
            }
            $node->types = $types;
            return $node;
        }

        throw new UnexpectedValueException('Unknown node type: ' . get_class($node));
    }
=======
>>>>>>> Stashed changes
}
