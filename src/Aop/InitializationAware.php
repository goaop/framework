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
 * Marker interface for proxies that expose initialization interceptor entry point.
 *
 * @template T of object
 */
interface InitializationAware
{
    /**
     * @param list<mixed> $arguments
     * @return T
     */
    public static function __aop__initialization(array $arguments = []): object;
}
