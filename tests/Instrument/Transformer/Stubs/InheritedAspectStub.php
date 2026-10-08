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

/**
 * Aspect that implements Go\Aop\Aspect only through its parent: the weaver must still skip it
 * when a pointcut matches its methods
 */
final class InheritedAspectStub extends BaseAspectStub
{
    public function beforeMethod(): void {}
}
