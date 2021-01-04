<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Node finder visitor for compatibility with PHP-Parser < 4.0
 */
class NodeFinderVisitor extends NodeVisitorAbstract
{
    /**
     * List of node class names to search
     *
     * @var string[]
     */
    protected array $searchNodes;

    /**
     * List of found nodes
     *
     * @var Node[] Found nodes
     */
    protected ?array $foundNodes = null;

    /**
     * Visitor constructor
     *
     * @param string[] $searchNodes List of node names to search in AST
     */
    public function __construct(array $searchNodes)
    {
        $this->searchNodes = $searchNodes;
    }

    /**
     * Get found nodes.
     *
     * Nodes are returned in pre-order.
     *
     * @return Node[] Found nodes
     */
    public function getFoundNodes(): array
    {
        return $this->foundNodes;
    }

    /**
     * Called once before traversal.
     *
     * Return value semantics:
     *  * null:      $nodes stays as-is
     *  * otherwise: $nodes is set to the return value
     *
     * @param Node[] $nodes Array of nodes
     *
     * @return null|Node[] Array of nodes
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->foundNodes = [];

        return null;
    }

    /**
     * Called when entering a node.
     *
     * Return value semantics:
     *  * null
     *        => $node stays as-is
     *  * NodeTraverser::DONT_TRAVERSE_CHILDREN
     *        => Children of $node are not traversed. $node stays as-is
     *  * NodeTraverser::STOP_TRAVERSAL
     *        => Traversal is aborted. $node stays as-is
     *  * otherwise
     *        => $node is set to the return value
     *
     * @param Node $node Node
     *
     * @return null|int|Node Node
     */
    public function enterNode(Node $node)
    {
        foreach ($this->searchNodes as $nodeClassName) {
            if ($node instanceof $nodeClassName) {
                $this->foundNodes[] = $node;
            }
        }

        return null;
    }
}
