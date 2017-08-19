<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\TestUtils;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard as Printer;

/**
 * Extracts join points definitions from woven class proxy by parsing and traversing class AST.
 */
final class JoinPointsExtractor extends NodeVisitorAbstract
{
    /**
     * @var null|Node\Expr\Array_
     */
    private $joinPoints = null;

    /**
     * @var null|Parser
     */
    private static $parser;

    /**
     * @var null|NodeTraverser
     */
    private static $traverser;

    /**
     * @var null|JoinPointsExtractor
     */
    private static $extractor;

    /**
     * @var null|Printer
     */
    private static $printer;

    /**
     * {@inheritdoc}
     */
    public function beforeTraverse(array $nodes)
    {
        $this->joinPoints = null;
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        if (
            $node instanceof Stmt\Property
            &&
            $node->isPrivate()
            &&
            $node->isStatic()
            &&
            '__joinPoints' === $node->props[0]->name
        ) {
            $this->joinPoints = $node->props[0]->default;
        }
    }

    /**
     * Get extracted value of private static "__joinPoints" property from woven class.
     *
     * @return Node\Expr\Array_
     */
    public function getExtractedAst()
    {
        return $this->joinPoints;
    }

    /**
     * Extract join points from woven class
     *
     * @param string $class Either PHP code of class, or path to class file.
     *
     * @return array Value of private static "__joinPoints" property.
     */
    public static function extractJoinPoints($class)
    {
        if (file_exists($class)) {
            $class = file_get_contents($class);
        }

        if (null === self::$parser) {
            self::$parser    = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
            self::$traverser = new NodeTraverser();
            self::$extractor = new JoinPointsExtractor();
            self::$printer   = new Printer();

            self::$traverser->addVisitor(self::$extractor);
        }

        $stmts = self::$parser->parse($class);
        self::$traverser->traverse($stmts);

        $ast = self::$extractor->getExtractedAst();

        return eval('return ' . self::$printer->prettyPrint([$ast]));
    }
}
