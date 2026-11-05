<?php

declare(strict_types=1);

namespace Go\Instrument\Transformer\Stubs;

use Go\Stubs\Collision\Interceptor as Product;
use Go\Stubs\Collision\The;

/**
 * Signature types colliding with the original imports and with the short names of generated references (#826):
 * App\Domain\Product with the imported Product alias, the global Exception and App\Domain\Money with classes of this
 * namespace, the local Proxy class with Go\Aop\Proxy
 */
#[\App\Attribute\Loggable]
class SignatureCollisionClass
{
    public ?Proxy $proxy = null;

    public function priceFor(\App\Domain\Product $product, Product $imported, string $level = The::LEVEL): \App\Domain\Money|Money
    {
        return new Money();
    }

    #[\App\Attribute\Loggable(\App\Domain\Product::class, level: Product::LEVEL)]
    public function combine((\Countable&\Traversable)|\App\Domain\Product|null $items, ?self $same = null, \Exception|Exception ...$errors): static
    {
        return $this;
    }
}
