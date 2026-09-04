<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.0 — attribute classes (issue #615).
 * The global Attribute and AllowDynamicProperties markers are compile-time
 * invalid on traits, so WeavingTransformer must remove them from the woven
 * trait. Only the incompatible entries of a grouped attribute are removed.
 * The proxy class copies the original attribute groups from the AST and keeps them.
 */
#[\FakeMarkerAttr]
trait TestGroupedAttributeClassOriginal
{
    public function name(): string
    {
        return 'grouped';
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php80-attribute-class.php';


trait TestDynamicPropertiesClassOriginal
{
    public function touch(): bool
    {
        return true;
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php80-attribute-class.php';


trait TestCustomAttributeOriginal
{
    public function __construct(private string $reason = 'none')
    {
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php80-attribute-class.php';
