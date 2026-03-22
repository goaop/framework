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

namespace Go\Proxy\Generator;

/**
 * Generates a PHP file with an optional namespace declaration and arbitrary PHP body.
 *
 * This is a lightweight file container — it does not produce AST nodes for the body
 * (body is arbitrary PHP code emitted verbatim after the namespace declaration).
 */
final class FileGenerator
{
    private ?string $namespace = null;
    private string $body       = '';

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    /**
     * Sets the PHP code body to emit after the namespace declaration.
     */
    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    /**
     * Generates the full PHP file source.
     */
    public function generate(): string
    {
        $parts = ['<?php', '', 'declare(strict_types=1);'];

        if ($this->namespace !== null && $this->namespace !== '') {
            $parts[] = '';
            $parts[] = 'namespace ' . $this->namespace . ';';
        }

        if ($this->body !== '') {
            $parts[] = '';
            $parts[] = $this->body;
        }

        return implode("\n", $parts) . "\n";
    }
}
