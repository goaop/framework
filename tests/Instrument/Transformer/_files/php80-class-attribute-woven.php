<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.0 — classes with class-level attributes (issue #598).
 * WeavingTransformer must skip the attribute groups when converting the class
 * to a trait: attributes are legal on traits and must be kept untouched —
 * except #[\Attribute]/#[\AllowDynamicProperties], which are compile-time
 * invalid on traits and are removed from the woven trait (issue #615).
 */
#[\FakeMarkerAttr]
trait TestClassWithPlainAttribute__AopProxied
{
    public function doSomething(): int
    {
        return 42;
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php80-class-attribute.php';


#[\FakeMarkerAttr]
trait TestClassWithArgumentAttribute__AopProxied
{
    public function doSomethingElse(): string
    {
        return 'else';
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php80-class-attribute.php';
