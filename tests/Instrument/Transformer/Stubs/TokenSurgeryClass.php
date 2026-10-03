<?php

declare(strict_types=1);

namespace Go\Instrument\Transformer\Stubs;

use Attribute as AttributeAlias;
use Override as Overrides;

/**
 * Weaving input whose declaration exercises the class-to-trait token surgery: an aliased trait-incompatible
 * attribute, a modifier on its own line, a multi-line comment in the header and the Override attribute written in
 * different ways next to an attribute that only ends with "Override"
 */
#[AttributeAlias]
final
class TokenSurgeryClass /* a comment
    spanning lines */ implements \Countable, \IteratorAggregate
{
    #[Overrides]
    public function count(): int
    {
        return 1;
    }

    #[\Override]
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator([]);
    }

    #[MyOverride]
    public function marker(): int
    {
        return 3;
    }
}
