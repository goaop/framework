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

namespace Go\Instrument\Transformer\Weaving;

use Go\Aop\Exception\WeavingException;
use Go\Instrument\Transformer\StreamMetaData;
use PhpParser\Node\Param;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TokenStreamEditorTest extends TestCase
{
    private const string SOURCE = "<?php\n#[First, Second(['a' => [1]])]\nfinal class Foo extends Bar\n{\n}";

    public function testRangeReturnsTokenPositionsOfNode(): void
    {
        $metadata = self::createMetadata(self::SOURCE);

        [$start, $end] = TokenStreamEditor::range(self::findClass($metadata));

        $this->assertSame('#[', $metadata->tokenStream[$start]->text);
        $this->assertSame('}', $metadata->tokenStream[$end]->text);
    }

    public function testRangeAfterAttributesStartsAfterLastAttributeGroup(): void
    {
        $metadata = self::createMetadata(self::SOURCE);

        [$start, $end] = TokenStreamEditor::rangeAfterAttributes(self::findClass($metadata));

        $this->assertSame("\n", $metadata->tokenStream[$start]->text);
        $this->assertSame('final', $metadata->tokenStream[$start + 1]->text);
        $this->assertSame('}', $metadata->tokenStream[$end]->text);
    }

    public function testRangeAfterAttributesOfNodeWithoutAttributesIsItsRange(): void
    {
        $metadata = self::createMetadata("<?php\nclass Foo {}");
        $class    = self::findClass($metadata);

        $this->assertSame(TokenStreamEditor::range($class), TokenStreamEditor::rangeAfterAttributes($class));
    }

    /**
     * @return iterable<string, array{\PhpParser\Node, string}>
     */
    public static function nodesWithoutTokenPositions(): iterable
    {
        yield 'declaration' => [new Class_('Detached'), 'Declaration of Detached has no token positions to weave'];
        yield 'other node' => [new Param(new Variable('x')), 'Declaration of Param has no token positions to weave'];
    }

    #[DataProvider('nodesWithoutTokenPositions')]
    public function testNodeWithoutTokenPositionsIsRejected(\PhpParser\Node $node, string $message): void
    {
        try {
            TokenStreamEditor::range($node);
            $this->fail('A node without token positions must be rejected');
        } catch (WeavingException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    public function testBlankKeepsOnlyNewlines(): void
    {
        $metadata = self::createMetadata("<?php\n\$a = [\n    1,\n];\necho 1;\n");
        $editor   = new TokenStreamEditor($metadata);

        $editor->blank(1, (int) $editor->find(';', 0, 20));

        $this->assertSame("<?php\n\n\n\necho 1;\n", $metadata->source);
    }

    public function testBlankPutsReplacementBeforeFirstRemainingToken(): void
    {
        $metadata = self::createMetadata("<?php echo 1; echo 2;\n");
        $editor   = new TokenStreamEditor($metadata);

        $editor->remove(1, 1);
        $editor->blank(1, 4, '/* gone */');

        $this->assertSame("<?php /* gone */ echo 2;\n", $metadata->source);
    }

    public function testBlankCanKeepWhitespace(): void
    {
        $metadata = self::createMetadata("<?php echo 1, 2;\n");
        $editor   = new TokenStreamEditor($metadata);

        $editor->blank(1, 6, keepWhitespace: true);

        $this->assertSame("<?php   ;\n", $metadata->source);
    }

    public function testRemoveDropsTokens(): void
    {
        $metadata = self::createMetadata("<?php echo 1; echo 2;\n");
        $editor   = new TokenStreamEditor($metadata);

        $editor->remove(1, 4);

        $this->assertSame("<?php  echo 2;\n", $metadata->source);
        $this->assertNull($editor->token(1));
    }

    public function testRemoveWithInlineWhitespaceDropsWhitespaceOnTheSameLine(): void
    {
        $metadata = self::createMetadata("<?php\nfinal class Foo {}\n");
        $editor   = new TokenStreamEditor($metadata);

        $editor->removeWithInlineWhitespace((int) $editor->find('final', 0, 10));

        $this->assertSame("<?php\nclass Foo {}\n", $metadata->source);
    }

    public function testRemoveWithInlineWhitespaceKeepsWhitespaceWithNewline(): void
    {
        $metadata = self::createMetadata("<?php\nfinal\nclass Foo {}\n");
        $editor   = new TokenStreamEditor($metadata);

        $editor->removeWithInlineWhitespace((int) $editor->find('final', 0, 10));

        $this->assertSame("<?php\n\nclass Foo {}\n", $metadata->source);
    }

    public function testReplaceAndAppendChangeTokenTextAndSkipRemovedTokens(): void
    {
        $metadata = self::createMetadata("<?php echo 1;\n");
        $editor   = new TokenStreamEditor($metadata);
        $echo     = (int) $editor->find('echo', 0, 10);

        $editor->replace($echo, 'print');
        $editor->append($echo, '/* a */');
        $editor->remove(0, 0);
        $editor->replace(0, 'lost');
        $editor->append(0, 'lost');

        $this->assertSame("print/* a */ 1;\n", $metadata->source);
    }

    public function testFindReturnsNullWithoutMatchInRange(): void
    {
        $editor = new TokenStreamEditor(self::createMetadata("<?php echo 1;\n"));

        $this->assertSame(4, $editor->find(';', 0, 10));
        $this->assertNull($editor->find(';', 0, 3));
    }

    private static function createMetadata(string $source): StreamMetaData
    {
        $stream = fopen('php://input', 'rb');
        assert(is_resource($stream));

        return new StreamMetaData($stream, $source);
    }

    private static function findClass(StreamMetaData $metadata): ClassLike
    {
        $class = new NodeFinder()->findFirstInstanceOf($metadata->syntaxTree, ClassLike::class);
        assert($class instanceof ClassLike);

        return $class;
    }
}
