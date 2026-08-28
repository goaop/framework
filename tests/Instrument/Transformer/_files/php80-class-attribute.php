<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.0 — classes with class-level attributes (issue #598).
 * WeavingTransformer must skip the attribute groups when converting the class
 * to a trait: attributes are legal on traits and must be kept untouched.
 */
#[\FakeMarkerAttr]
class TestClassWithPlainAttribute
{
    public function doSomething(): int
    {
        return 42;
    }
}

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
#[\FakeMarkerAttr]
final class TestClassWithArgumentAttribute
{
    public function doSomethingElse(): string
    {
        return 'else';
    }
}
