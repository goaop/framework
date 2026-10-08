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

use Go\Aop\Aspect;

/**
 * Base aspect: InheritedAspectStub is an aspect only through this parent
 */
abstract class BaseAspectStub implements Aspect {}
