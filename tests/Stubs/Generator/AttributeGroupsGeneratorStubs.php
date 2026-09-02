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

namespace Go\Stubs\Generator;

use Attribute;

#[Attribute(Attribute::TARGET_ALL)]
class TestNoArgsAttr {}

#[Attribute(Attribute::TARGET_ALL)]
class TestArgsAttr
{
    public function __construct(
        public string $value,
        public int $count = 1,
    ) {}
}

#[Attribute(Attribute::TARGET_ALL)]
class TestNamedArgsAttr
{
    public function __construct(
        public string $label = '',
        public bool $enabled = true,
    ) {}
}

#[TestNoArgsAttr]
function attrGenHelper_noArgs(): void {}

#[TestArgsAttr('hello', 3)]
function attrGenHelper_withArgs(): void {}

#[TestNamedArgsAttr(label: 'test', enabled: false)]
function attrGenHelper_namedArgs(): void {}

#[TestNoArgsAttr]
#[TestArgsAttr('multi')]
function attrGenHelper_multipleAttrs(): void {}

#[TestNoArgsAttr]
class AttrGenHelperClass
{
    #[TestNoArgsAttr]
    public string $annotatedProp = '';

    #[TestArgsAttr('method_value')]
    public function annotatedMethod(): void {}

    public function methodWithAttrParam(#[TestNoArgsAttr] string $name): void {}
}

enum TestStatusEnum: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}

#[Attribute(Attribute::TARGET_ALL)]
class TestRichAttr
{
    public function __construct(
        public mixed $value = null,
        public mixed $extra = null,
    ) {}
}

#[TestRichAttr(TestStatusEnum::Active)]
function attrGenHelper_enumArg(): void {}

#[TestRichAttr(new \ArrayObject([1, 2]))]
function attrGenHelper_objectArg(): void {}

#[TestRichAttr(PHP_INT_MAX)]
function attrGenHelper_globalConstArg(): void {}

class AttrGenRichHelperClass
{
    #[TestRichAttr(TestStatusEnum::Disabled, 123)]
    public function tagged(#[TestRichAttr(TestStatusEnum::Active)] int $x = 8): int
    {
        return $x;
    }

    #[TestRichAttr(PHP_INT_MAX)]
    public function limited(): int
    {
        return PHP_INT_MAX;
    }
}
