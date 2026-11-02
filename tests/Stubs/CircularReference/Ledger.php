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

namespace Go\Stubs\CircularReference;

use Closure;

/**
 * Dependency of LedgerAspect whose constructor runs code given by the test, e.g. an interceptor of the aspect
 */
final class Ledger
{
    public function __construct(
        public readonly string $name,
        Closure $onConstruct,
    ) {
        $this->open($onConstruct);
    }

    private function open(Closure $onConstruct): void
    {
        $onConstruct();
    }
}
