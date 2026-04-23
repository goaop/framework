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

namespace Go\Aop;

/**
 * Marker interface for proxies that expose static initialization interceptor entry point.
 */
interface StaticInitializationAware
{
    public static function __aop__staticInitialization(): void;
}
