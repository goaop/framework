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

use Closure;
use Go\Aop\Exception\WeavingException;
use Go\Instrument\Transformer\StreamMetaData;
use PhpParser\Node\Attribute;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;

final class TokenStreamEditorTest extends TestCase
{
    private const string SOURCE = <<<'PHP'
        <?php
        #[First, Second(['a' => [1]])]
        final class Foo extends Bar
        {
        }
        PHP;

    public function testRangeOfReturnsTokenPositionsOfNode(): void
    {
        $metadata = self::createMetadata(self::SOURCE);
        $range    = TokenStreamEditor::rangeOf(self::findClass($metadata));

        $this->assertNotNull($range);
        $this->assertSame('#[', $metadata->tokenStream[$range[0]]->text);
        $this->assertSame('}', $metadata->tokenStream[$range[1]]->text);
    }

    public function testRangeOfNodeWithoutTokenPositionsIsNull(): void
    {
        $this->assertNull(TokenStreamEditor::rangeOf(new Class_('Detached')));
    }

    public function testDeclarationRangeStartsAfterAttributeGroups(): void
    {
        $metadata = self::createMetadata(self::SOURCE);

        [$start, $end] = TokenStreamEditor::declarationRange(self::findClass($metadata));

        $this->assertSame("\n", $metadata->tokenStream[$start]->text);
        $this->assertSame('final', $metadata->tokenStream[$start + 1]->text);
        $this->assertSame('}', $metadata->tokenStream[$end]->text);
    }

    public function testDeclarationWithoutTokenPositionsIsRejected(): void
    {
        try {
            TokenStreamEditor::declarationRange(new Class_('Detached'));
            $this->fail('A declaration without token positions must be rejected');
        } catch (WeavingException $exception) {
            $this->assertSame('Declaration of Detached has no token positions to weave', $exception->getMessage());
        }
    }

    public function testBlankKeepsOnlyNewlines(): void
    {
        $metadata = self::createMetadata("<?php\n\$a = [\n    1,\n];\necho 1;\n");
        $editor   = new TokenStreamEditor($metadata);
        $end      = (int) $editor->find(';', 0, count($metadata->tokenStream) - 1);

        $editor->blank(1, $end);

        $this->assertSame("<?php\n\n\n\necho 1;\n", $metadata->source);
    }

    public function testRemoveDropsTokensAndSkipsRemovedPositions(): void
    {
        $metadata = self::createMetadata("<?php echo 1; echo 2;\n");
        $editor   = new TokenStreamEditor($metadata);

        $editor->remove(1, 4);
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

    public function testReplaceAppendAndPrependChangeTokenText(): void
    {
        $metadata = self::createMetadata("<?php echo 1;\n");
        $editor   = new TokenStreamEditor($metadata);
        $echo     = (int) $editor->find('echo', 0, 10);

        $editor->replace($echo, 'print');
        $editor->append($echo, '/* a */');
        $editor->remove(0, 0);
        $editor->prependToFirstToken(0, 10, '<?php ');

        $this->assertSame("<?php print/* a */ 1;\n", $metadata->source);
    }

    public function testFindReturnsNullOutsideRange(): void
    {
        $editor = new TokenStreamEditor(self::createMetadata("<?php echo 1;\n"));

        $this->assertNull($editor->find(';', 0, 2));
        $this->assertNull($editor->find('?', 0, 10));
    }

    public function testSkipAttributeGroupHandlesNestedBrackets(): void
    {
        $metadata = self::createMetadata(self::SOURCE);
        $editor   = new TokenStreamEditor($metadata);
        $start    = (int) $editor->find('#[', 0, 10);

        $afterGroup = $editor->skipAttributeGroup($start, count($metadata->tokenStream) - 1);

        $this->assertSame("\n", $metadata->tokenStream[$afterGroup]->text);
        $this->assertSame(']', $metadata->tokenStream[$afterGroup - 1]->text);
    }

    public function testRemoveAttributesBlanksGroupWithoutRemainingAttributes(): void
    {
        $metadata = self::createMetadata(self::SOURCE);

        new TokenStreamEditor($metadata)->removeAttributes(self::findClass($metadata)->attrGroups, static fn(): bool => true);

        $this->assertSame("<?php\n\nfinal class Foo extends Bar\n{\n}", $metadata->source);
    }

    public function testRemoveAttributesRemovesEntryWithTrailingComma(): void
    {
        $metadata = self::createMetadata(self::SOURCE);

        new TokenStreamEditor($metadata)->removeAttributes(self::findClass($metadata)->attrGroups, self::named('First'));

        $this->assertSame("<?php\n#[Second(['a' => [1]])]\nfinal class Foo extends Bar\n{\n}", $metadata->source);
    }

    public function testRemoveAttributesRemovesLastEntryWithLeadingComma(): void
    {
        $metadata = self::createMetadata(self::SOURCE);

        new TokenStreamEditor($metadata)->removeAttributes(self::findClass($metadata)->attrGroups, self::named('Second'));

        $this->assertSame("<?php\n#[First ]\nfinal class Foo extends Bar\n{\n}", $metadata->source);
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

    /**
     * @return Closure(Attribute): bool
     */
    private static function named(string $name): Closure
    {
        return static fn(Attribute $attribute): bool => $attribute->name->toString() === $name;
    }
}
