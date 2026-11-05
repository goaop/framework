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

// PHP 8.6-only syntax (#[\Override] on an enum case). This file must only be loaded from tests gated with
// #[RequiresPhp('>= 8.6.0-dev')].

namespace Go\Stubs;

interface Php86HasDefaultMode
{
    public const DEFAULT = 'none';
}

/**
 * Enum case overriding an interface constant, marked with #[\Override] (PHP 8.6, #794)
 */
enum Php86OverrideEnumCase: string implements Php86HasDefaultMode
{
    #[\Override]
    case DEFAULT = 'none';

    case Fast = 'fast';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
