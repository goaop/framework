<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Generator;

/**
 * Generates a PHP docblock comment.
 *
 * For raw docblocks (from existing PHP source), use the static factory
 * {@see fromDocComment()} which preserves the original text as-is.
 */
final class DocBlockGenerator
{
    private string $shortDescription;
    private string $longDescription;

    /** @var array<string, string[]> tagName => list of tag content lines */
    private array $tags = [];

    /** Holds a raw docblock string when constructed via fromDocComment() */
    private ?string $rawDocComment = null;

    public function __construct(string $shortDescription = '', string $longDescription = '')
    {
        $this->shortDescription = $shortDescription;
        $this->longDescription  = $longDescription;
    }

    /**
     * Creates a DocBlockGenerator from an existing raw docblock string.
     * The docblock is preserved verbatim — no parsing is performed.
     */
    public static function fromDocComment(string $rawDocComment): self
    {
        $instance                 = new self();
        $instance->rawDocComment  = $rawDocComment;

        return $instance;
    }

    /**
     * Adds a tag line to the docblock.
     *
     * @param string $tagName  e.g. 'var', 'param', 'return'
     * @param string $content  e.g. 'array<string, int>'
     */
    public function addTag(string $tagName, string $content): void
    {
        $this->tags[$tagName][] = $content;
    }

    /**
     * Renders the docblock as a PHP comment string.
     */
    public function generate(): string
    {
        if ($this->rawDocComment !== null) {
            return $this->rawDocComment;
        }

        $lines = ['/**'];

        if ($this->shortDescription !== '') {
            $lines[] = ' * ' . $this->shortDescription;
        }

        if ($this->longDescription !== '') {
            if ($this->shortDescription !== '') {
                $lines[] = ' *';
            }
            foreach (explode("\n", $this->longDescription) as $descLine) {
                $lines[] = ' * ' . $descLine;
            }
        }

        foreach ($this->tags as $tagName => $contents) {
            if ($this->shortDescription !== '' || $this->longDescription !== '') {
                $lines[] = ' *';
            }
            foreach ($contents as $content) {
                $lines[] = " * @{$tagName} {$content}";
            }
        }

        $lines[] = ' */';

        return implode("\n", $lines);
    }
}
