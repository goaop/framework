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

use Go\Aop\Aspect;

/**
 * Aspect loaded only by ContainerTest to simulate weaving re-entering the container while autoloading
 */
final class ReentrantAutoloadAspect implements Aspect
{
    public string $name = 'reentrant';
}
