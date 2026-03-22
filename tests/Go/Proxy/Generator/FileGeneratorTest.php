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

class FileGeneratorTest extends TestCase
{
    public function testGenerateWithNamespace(): void
    {
        $gen = new FileGenerator();
        $gen->setNamespace('My\Namespace');
        $gen->setBody('$x = 1;');
        $output = $gen->generate();
        $this->assertStringContainsString('<?php', $output);
        $this->assertStringContainsString("declare(strict_types=1)", $output);
        $this->assertStringContainsString('namespace My\Namespace', $output);
        $this->assertStringContainsString('$x = 1;', $output);
    }

    public function testGenerateWithoutNamespace(): void
    {
        $gen = new FileGenerator();
        $gen->setBody('echo "hello";');
        $output = $gen->generate();
        $this->assertStringContainsString('<?php', $output);
        $this->assertStringNotContainsString('namespace', $output);
        $this->assertStringContainsString('echo "hello"', $output);
    }

    public function testGenerateEmptyBody(): void
    {
        $gen = new FileGenerator();
        $output = $gen->generate();
        $this->assertStringContainsString('<?php', $output);
    }

    public function testStrictTypesDeclaration(): void
    {
        $gen = new FileGenerator();
        $output = $gen->generate();
        $this->assertStringContainsString('declare(strict_types=1)', $output);
    }
}
