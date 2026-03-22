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

use PHPUnit\Framework\TestCase;

class DocBlockGeneratorTest extends TestCase
{
    public function testGenerateWithShortDescOnly(): void
    {
        $gen = new DocBlockGenerator('Short description.');
        $output = $gen->generate();
        $this->assertStringContainsString('Short description.', $output);
        $this->assertStringStartsWith('/**', $output);
        $this->assertStringEndsWith('*/', $output);
    }

    public function testGenerateWithShortAndLongDesc(): void
    {
        $gen = new DocBlockGenerator('Short.', 'Long description here.');
        $output = $gen->generate();
        $this->assertStringContainsString('Short.', $output);
        $this->assertStringContainsString('Long description here.', $output);
    }

    public function testAddTag(): void
    {
        $gen = new DocBlockGenerator('Test');
        $gen->addTag('param', 'string $name');
        $gen->addTag('return', 'void');
        $output = $gen->generate();
        $this->assertStringContainsString('@param string $name', $output);
        $this->assertStringContainsString('@return void', $output);
    }

    public function testFromDocComment(): void
    {
        $raw = "/**\n * Existing comment.\n * @see SomeClass\n */";
        $gen = DocBlockGenerator::fromDocComment($raw);
        $output = $gen->generate();
        $this->assertSame($raw, $output);
    }

    public function testEmptyDocBlock(): void
    {
        $gen = new DocBlockGenerator();
        $output = $gen->generate();
        $this->assertStringStartsWith('/**', $output);
        $this->assertStringEndsWith('*/', $output);
    }

    public function testGenerateContainsStarLines(): void
    {
        $gen = new DocBlockGenerator('My doc.');
        $output = $gen->generate();
        // Each content line should start with ' * '
        $lines = explode("\n", $output);
        foreach (array_slice($lines, 1, -1) as $line) {
            $this->assertStringStartsWith(' *', $line);
        }
    }
}
