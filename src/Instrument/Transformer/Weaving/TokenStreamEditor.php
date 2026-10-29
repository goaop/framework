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
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\ClassLike;
use PhpToken;

/**
 * Line-preserving edits of the token stream of one source file
 *
 * Every edit keeps the line count of the file: blanked tokens keep their newlines and only newline-free whitespace is
 * dropped next to a removed token, so the woven code stays on the original lines (XDebug breakpoint mapping).
 * Positions are the keys of {@see StreamMetaData::$tokenStream}; a removed position is skipped by every scan.
 *
 * @internal
 */
final readonly class TokenStreamEditor
{
    public function __construct(
        /** Source file whose token stream is edited in place */
        private StreamMetaData $metadata,
    ) {}

    /**
     * Returns the positions of the first and the last token of a node, null for a node without token positions
     *
     * @return array{int, int}|null
     */
    public static function rangeOf(Node $node): ?array
    {
        $start = $node->getAttribute('startTokenPos');
        $end   = $node->getAttribute('endTokenPos');

        return is_int($start) && is_int($end) ? [$start, $end] : null;
    }

    /**
     * Returns the token range of a class-like declaration without its attribute groups
     *
     * A ClassLike node's startTokenPos includes its attribute groups (`#[...]`), so scanning from there would rename
     * the first T_STRING inside the attribute to the trait name and then delete the real class header (issue #598).
     * The range starts right after the last attribute group and ends with the closing brace, so a scan of it never
     * leaves the declaration.
     *
     * @return array{int, int} Positions of the first and the last token to scan
     *
     * @throws WeavingException When the node was parsed without token positions
     */
    public static function declarationRange(ClassLike $classNode): array
    {
        $range = self::rangeOf($classNode);
        if ($range === null) {
            throw WeavingException::declarationWithoutTokenPositions((string) $classNode->name);
        }
        $lastAttrGroup = end($classNode->attrGroups);
        if ($lastAttrGroup !== false) {
            $attrGroupsEnd = $lastAttrGroup->getAttribute('endTokenPos');
            if (is_int($attrGroupsEnd)) {
                $range[0] = $attrGroupsEnd + 1;
            }
        }

        return $range;
    }

    /**
     * Returns the token at the position, null when it was removed
     */
    public function token(int $position): ?PhpToken
    {
        return $this->metadata->tokenStream[$position] ?? null;
    }

    /**
     * Returns the position of the first token in [$start, $end] with the given text, null when there is none
     */
    public function find(string $text, int $start, int $end): ?int
    {
        for ($position = $start; $position <= $end; ++$position) {
            if ($this->token($position)?->text === $text) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Returns the position right after the attribute group opened by the `#[` token at $position
     *
     * Attribute arguments can contain arbitrary nested brackets, so the brackets are counted up to the closing one.
     * The scan never goes beyond $end.
     */
    public function skipAttributeGroup(int $position, int $end): int
    {
        $bracketDepth = 1;
        ++$position;
        while ($position <= $end && $bracketDepth > 0) {
            $text = $this->token($position)?->text;
            if ($text === '[') {
                ++$bracketDepth;
            } elseif ($text === ']') {
                --$bracketDepth;
            }
            ++$position;
        }

        return $position;
    }

    /**
     * Replaces the text of the token at the position
     */
    public function replace(int $position, string $text): void
    {
        $token = $this->token($position);
        if ($token !== null) {
            $token->text = $text;
        }
    }

    /**
     * Appends a text to the token at the position
     */
    public function append(int $position, string $text): void
    {
        $token = $this->token($position);
        if ($token !== null) {
            $token->text .= $text;
        }
    }

    /**
     * Prepends a text to the first token of the [$start, $end] range that is still present
     */
    public function prependToFirstToken(int $start, int $end, string $text): void
    {
        for ($position = $start; $position <= $end; ++$position) {
            $token = $this->token($position);
            if ($token !== null) {
                $token->text = $text . $token->text;

                return;
            }
        }
    }

    /**
     * Blanks out all tokens in [$start, $end], keeping only the newlines they contained
     *
     * Token objects are kept in place (text emptied) instead of being removed, so the iteration order of the token
     * stream is untouched and the line budget of the file is preserved.
     */
    public function blank(int $start, int $end): void
    {
        for ($position = $start; $position <= $end; ++$position) {
            $token = $this->token($position);
            if ($token !== null) {
                $token->text = str_repeat("\n", substr_count($token->text, "\n"));
            }
        }
    }

    /**
     * Removes all tokens in [$start, $end]
     */
    public function remove(int $start, int $end): void
    {
        for ($position = $start; $position <= $end; ++$position) {
            unset($this->metadata->tokenStream[$position]);
        }
    }

    /**
     * Removes the token at the position together with the whitespace after it, unless that one holds a newline
     *
     * Used for modifiers and commas: `final class Foo` becomes `class Foo`, while `final\nclass Foo` keeps its lines.
     */
    public function removeWithInlineWhitespace(int $position): void
    {
        unset($this->metadata->tokenStream[$position]);
        $nextToken = $this->token($position + 1);
        if ($nextToken !== null && $nextToken->id === T_WHITESPACE && strpbrk($nextToken->text, "\r\n") === false) {
            unset($this->metadata->tokenStream[$position + 1]);
        }
    }

    /**
     * Removes the attributes selected by $shouldRemove from the given attribute groups
     *
     * A group whose attributes are all removed is blanked out entirely; otherwise only the selected entries are
     * removed together with one adjacent comma, the rest of the group is kept.
     *
     * @param AttributeGroup[]         $attrGroups
     * @param Closure(Attribute): bool $shouldRemove
     */
    public function removeAttributes(array $attrGroups, Closure $shouldRemove): void
    {
        foreach ($attrGroups as $attrGroup) {
            $attributesToRemove = array_filter($attrGroup->attrs, $shouldRemove);
            if ($attributesToRemove === []) {
                continue;
            }
            if (count($attributesToRemove) === count($attrGroup->attrs)) {
                $groupRange = self::rangeOf($attrGroup);
                if ($groupRange !== null) {
                    $this->blank(...$groupRange);
                }
                continue;
            }
            foreach ($attributesToRemove as $attribute) {
                $attributeRange = self::rangeOf($attribute);
                if ($attributeRange === null) {
                    continue;
                }
                $this->blank(...$attributeRange);
                $this->removeAdjacentComma(...$attributeRange);
            }
        }
    }

    /**
     * Removes one comma adjacent to the removed [$start, $end] list entry
     *
     * Prefers the trailing comma (after $end) and falls back to the leading one (before $start) when the entry was
     * the last one of the list. Blank and whitespace tokens between the entry and the comma are skipped.
     */
    private function removeAdjacentComma(int $start, int $end): void
    {
        for ($position = $end + 1; ($token = $this->token($position)) !== null; ++$position) {
            if ($token->text === ',') {
                $this->removeWithInlineWhitespace($position);

                return;
            }
            if (!self::isBlank($token)) {
                break;
            }
        }
        for ($position = $start - 1; ($token = $this->token($position)) !== null; --$position) {
            if ($token->text === ',') {
                unset($this->metadata->tokenStream[$position]);

                return;
            }
            if (!self::isBlank($token)) {
                break;
            }
        }
    }

    /**
     * Checks whether the token is whitespace or was blanked out
     */
    private static function isBlank(PhpToken $token): bool
    {
        return $token->id === T_WHITESPACE || $token->text === '';
    }
}
