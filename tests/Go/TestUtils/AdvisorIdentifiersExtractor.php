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
 * Extracts advisor identifiers from woven class proxy without loading class into memory.
 */
final class AdvisorIdentifiersExtractor extends NodeVisitorAbstract
{
    /**
     * @var null|Node\Expr\Array_
     */
    private $advisorIdentifiers = null;

    /**
     * @var null|Parser
     */
    private static $parser;

    /**
     * @var null|NodeTraverser
     */
    private static $traverser;

    /**
     * @var null|AdvisorIdentifiersExtractor
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
        $this->advisorIdentifiers = null;
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        if (!$node instanceof Stmt\Property) {
            return;
        }

        if (!$node->isPrivate()) {
            return;
        }

        if (!$node->isStatic()) {
            return;
        }

        if (!'__joinPoints' === $node->props[0]->name) {
            return;
        }

        $this->advisorIdentifiers = $node->props[0]->default;
    }

    /**
     * Get extracted value of private static "__joinPoints" property from woven class.
     *
     * @return Node\Expr\Array_
     */
    public function getExtractedAst()
    {
        return $this->advisorIdentifiers;
    }

    /**
     * Extract advisor identifiers from woven class without loading class into memory.
     *
     * @param string $class Either PHP code of class, or path to class file.
     *
     * @return array Evaluated value of private static "__joinPoints" property.
     */
    public static function extract($class)
    {
        if (file_exists($class)) {
            $class = file_get_contents($class);
        }

        if (null === self::$parser) {
            self::$parser    = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
            self::$traverser = new NodeTraverser();
            self::$extractor = new AdvisorIdentifiersExtractor();
            self::$printer   = new Printer();

            self::$traverser->addVisitor(self::$extractor);
        }

        $stmts = self::$parser->parse($class);
        self::$traverser->traverse($stmts);

        $ast = self::$extractor->getExtractedAst();

        return eval('return ' . self::$printer->prettyPrint([$ast]));
    }
}
