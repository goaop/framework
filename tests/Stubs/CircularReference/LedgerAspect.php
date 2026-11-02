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

use Go\Aop\Aspect;
use Go\Aop\Intercept\Joinpoint;

/**
 * Aspect depending on a service that runs the advices of this aspect (LazyServiceCreationTest)
 */
final class LedgerAspect implements Aspect
{
    /**
     * @var list<string> Joinpoints the advices ran at
     */
    public array $calls = [];

    public function __construct(
        private readonly Ledger $ledger,
    ) {}

    /**
     * Reads a property of the aspect: run on the lazy aspect, it creates the aspect first
     */
    public function beforeRecord(Joinpoint $joinpoint): void
    {
        $this->calls[] = $this->ledger->name . ':' . $joinpoint;
    }

    /**
     * Reads no property of the aspect: run on the lazy aspect, it does not create the aspect
     */
    public function beforeOpen(Joinpoint $joinpoint): void {}
}
