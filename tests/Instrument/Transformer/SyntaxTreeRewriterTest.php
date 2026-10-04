<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Closure;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\MagicConst\File;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PHPUnit\Framework\TestCase;

class SyntaxTreeRewriterTest extends TestCase
{
    /**
     * Rewriter under test, reachable from the rules for nested transformations
     */
    private ?SyntaxTreeRewriter $rewriter = null;

    public function testRulesReceiveNodesOfDeclaredTypesAndSubtypes(): void
    {
        $visited = [];
        $rule    = $this->createRule([MagicConst::class, String_::class], function (Node $node) use (&$visited): bool {
            $visited[] = $node::class;

            return false;
        });

        (new SyntaxTreeRewriter($rule))->transform($this->createMetadata('<?php echo __DIR__, "x", __FILE__, 42;'));

        $this->assertSame([Dir::class, String_::class, File::class], $visited);
    }

    public function testRulesForTheSameNodeAreAppliedInTheirOrder(): void
    {
        $calls = [];
        $first = $this->createRule([String_::class], function () use (&$calls): bool {
            $calls[] = 'first';

            return false;
        });
        $second = $this->createRule([Node\Scalar::class], function () use (&$calls): bool {
            $calls[] = 'second';

            return false;
        });

        (new SyntaxTreeRewriter($first, $second))->transform($this->createMetadata('<?php echo "a", "b";'));

        $this->assertSame(['first', 'second', 'first', 'second'], $calls);
    }

    public function testResultIsTransformedOnlyWhenAnyRuleChangedTheTokens(): void
    {
        $unchanged = $this->createRule([String_::class], fn(): bool => false);
        $changed   = $this->createRule([String_::class], function (Node $node, StreamMetaData $file): bool {
            $position = $node->getAttribute('startTokenPos');
            $this->assertIsInt($position);
            $file->tokenStream[$position]->text = '"rewritten"';

            return true;
        });

        $metadata = $this->createMetadata('<?php echo "a";');
        $this->assertSame(TransformerResult::Abstain, (new SyntaxTreeRewriter())->transform($metadata));
        $this->assertSame(TransformerResult::Abstain, (new SyntaxTreeRewriter($unchanged))->transform($metadata));
        $this->assertSame('<?php echo "a";', $metadata->source);

        $this->assertSame(TransformerResult::Transformed, (new SyntaxTreeRewriter($unchanged, $changed))->transform($metadata));
        $this->assertSame('<?php echo "rewritten";', $metadata->source);
    }

    public function testRulesReceiveTheAncestorsOfTheNode(): void
    {
        $ancestors = [];
        $rule      = $this->createRule([String_::class], function (Node $node, StreamMetaData $file, array $nodeAncestors) use (&$ancestors): bool {
            $ancestors[] = array_map(fn(Node $ancestor): string => $ancestor::class, $nodeAncestors);

            return false;
        });

        (new SyntaxTreeRewriter($rule))->transform($this->createMetadata('<?php function f() { echo "a"; } echo "b";'));

        // The parsed file is wrapped into a global namespace node
        $this->assertSame(
            [[Namespace_::class, Function_::class, Echo_::class], [Namespace_::class, Echo_::class]],
            $ancestors,
        );
    }

    /**
     * Weaving may load and transform another file while a file is still being transformed, the
     * walk over the outer file must continue unaffected
     */
    public function testNestedTransformationDoesNotDisturbTheOuterWalk(): void
    {
        $visited = [];
        $rule    = $this->createRule([New_::class], function (Node $node) use (&$visited): bool {
            $this->assertInstanceOf(New_::class, $node);
            $this->assertInstanceOf(Node\Name::class, $node->class);
            $className = $node->class->toString();
            $visited[] = $className;
            if ($className === 'Outer') {
                $this->assertInstanceOf(SyntaxTreeRewriter::class, $this->rewriter);
                $this->rewriter->transform($this->createMetadata('<?php new Inner;'));
            }

            return true;
        });
        $this->rewriter = new SyntaxTreeRewriter($rule);

        $result = $this->rewriter->transform($this->createMetadata('<?php new Outer; new Next;'));

        $this->assertSame(TransformerResult::Transformed, $result);
        $this->assertSame(['Outer', 'Inner', 'Next'], $visited);
    }

    /**
     * @param list<class-string<Node>>                  $nodeTypes
     * @param Closure(Node, StreamMetaData, list<Node>): bool $rewrite
     */
    private function createRule(array $nodeTypes, Closure $rewrite): NodeRewriter
    {
        return new readonly class ($nodeTypes, $rewrite) implements NodeRewriter {
            /**
             * @param list<class-string<Node>>                        $nodeTypes
             * @param Closure(Node, StreamMetaData, list<Node>): bool $rewrite
             */
            public function __construct(private array $nodeTypes, private Closure $rewrite) {}

            public function getNodeTypes(): array
            {
                return $this->nodeTypes;
            }

            public function rewriteNode(Node $node, StreamMetaData $file, array $ancestors): bool
            {
                return ($this->rewrite)($node, $file, $ancestors);
            }
        };
    }

    private function createMetadata(string $source): StreamMetaData
    {
        $stream = fopen('php://input', 'rb');
        assert($stream !== false);

        return new StreamMetaData($stream, $source);
    }
}
