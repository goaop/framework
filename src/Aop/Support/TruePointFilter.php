<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\PointFilter;

/**
 * Canonical PointFilter instance that matches all points.
 */
class TruePointFilter implements PointFilter
{
    /**
     * Private class constructor
     */
    private function __construct()
    {
    }

    /**
     * Singleton pattern
     */
    public static function getInstance(): self
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * @inheritdoc
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null): bool
    {
        return true;
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int
    {
        return self::KIND_ALL;
    }
}
