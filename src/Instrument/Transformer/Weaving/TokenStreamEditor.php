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
use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpToken;

/**
 * Line-preserving edits of the token stream of one source file, addressed by the token positions of AST nodes
 *
 * Every edit keeps the line count of the file: blanked tokens keep their newlines and only newline-free whitespace is
 * dropped next to a removed token, so the woven code stays on the original lines (XDebug breakpoint mapping).
 * Positions are the keys of {@see StreamMetaData::$tokenStream}, as recorded by the parser in the `startTokenPos` and
 * `endTokenPos` node attributes; a removed position is skipped by every edit.
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
     * Returns the positions of the first and the last token of a node
     *
     * @return array{int, int}
     *
     * @throws WeavingException When the node was parsed without token positions
     */
    public static function range(Node $node): array
    {
        $start = $node->getAttribute('startTokenPos');
        $end   = $node->getAttribute('endTokenPos');
        if (!is_int($start) || !is_int($end)) {
            throw WeavingException::declarationWithoutTokenPositions(
                $node instanceof ClassLike ? (string) $node->name : $node->getType(),
            );
        }

        return [$start, $end];
    }

    /**
     * Returns the token range of a declaration without its attribute groups
     *
     * The start position of a node includes its attribute groups (`#[...]`), so the declaration itself (modifiers,
     * keyword, name) starts right after the last group (issue #598).
     *
     * @return array{int, int}
     *
     * @throws WeavingException When the node was parsed without token positions
     */
    public static function rangeAfterAttributes(ClassLike|Param $node): array
    {
        $range         = self::range($node);
        $lastAttrGroup = end($node->attrGroups);
        if ($lastAttrGroup !== false) {
            $range[0] = self::range($lastAttrGroup)[1] + 1;
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
     * Blanks out the tokens in [$start, $end], keeping only the newlines they contained
     *
     * Token objects are kept in place (text emptied) instead of being removed, so the line budget of the file is
     * preserved. The $replacement text is put in front of the first blanked token; it must hold no newline.
     *
     * @param bool $keepWhitespace Leaves whitespace tokens untouched, e.g. to keep the original brace placement
     */
    public function blank(int $start, int $end, string $replacement = '', bool $keepWhitespace = false): void
    {
        for ($position = $start; $position <= $end; ++$position) {
            $token = $this->token($position);
            if ($token === null || ($keepWhitespace && $token->id === T_WHITESPACE)) {
                continue;
            }
            $token->text = $replacement . str_repeat("\n", substr_count($token->text, "\n"));
            $replacement = '';
        }
    }

    /**
     * Removes the tokens in [$start, $end]
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
}
