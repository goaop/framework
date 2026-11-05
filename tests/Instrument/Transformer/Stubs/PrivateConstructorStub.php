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

namespace Go\Instrument\Transformer\Stubs;

use Go\Instrument\Transformer\ConstructorExecutionTransformer;

/**
 * Class with a private constructor, created by its own static factory through ConstructorExecutionTransformer
 * (the `new static` of the factory is rewritten in woven files).
 */
class PrivateConstructorStub
{
    private function __construct(public string $name = 'default')
    {
    }

    public static function create(string $name): static
    {
        $instance = ConstructorExecutionTransformer::getInstance()->__call(static::class, [$name]);
        assert($instance instanceof static);

        return $instance;
    }
}
