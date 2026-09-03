<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.0 — the trait-incompatible marker is the LAST entry of a grouped attribute
 * (issue #615). WeavingTransformer must then remove the *leading* comma instead of
 * the trailing one, keeping `#[\FakeMarkerAttr]` intact on the woven trait. The group
 * is spread over several lines so that whitespace has to be skipped in both directions
 * while the adjacent comma is looked up.
 */
#[
    \FakeMarkerAttr,
    \Attribute
]
class TestLastGroupedAttributeClass
{
    public function name(): string
    {
        return 'last';
    }
}
