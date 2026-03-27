<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.3+ — class whose intercepted methods carry the Override attribute
 * combined with another attribute in the same group.
 * WeavingTransformer must strip only the Override attribute, preserving the others.
 */
class TestClassWithMultiAttrOverride
{
    #[\Override, \FakeAttr]
    public function overriddenFirst(): string
    {
        return 'first';
    }

    #[\FakeAttr, \Override]
    public function overriddenLast(): string
    {
        return 'last';
    }
}
