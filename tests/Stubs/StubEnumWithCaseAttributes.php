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

namespace Go\Stubs;

/**
 * Stub enum with attributes and doc comments on its cases, used by EnumProxyGeneratorTest (#794)
 */
enum StubEnumWithCaseAttributes: string
{
    /**
     * The active one
     */
    #[StubAttribute('active')]
    case Active = 'active';

    case Inactive = 'inactive';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
