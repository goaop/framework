<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * The `abstract` modifier must be removed by convertClassToTrait(): the trait keyword
 * itself accepts no class modifiers, while abstract *methods* are legal inside a trait.
 */
abstract class TestAbstractClass
{
    abstract public function abstractMethod(): string;

    public function concreteMethod(): int
    {
        return 1;
    }
}
