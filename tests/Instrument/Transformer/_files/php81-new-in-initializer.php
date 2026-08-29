<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

/**
 * Class with an intercepted promoted constructor property whose default value is a
 * new-in-initializer expression (issue #616).
 *
 * `new` is legal in a constructor parameter default but illegal in a property initializer,
 * so the proxy hook property must not carry this default — the value is supplied by the
 * constructor assignment injected by the promoted-parameter demotion.
 */
class NewInInitializerClass
{
    public function __construct(
        private \ArrayObject $bag = new \ArrayObject(['seed']),
    ) {
    }

    public function getBagItems(): array
    {
        return $this->bag->getArrayCopy();
    }
}
