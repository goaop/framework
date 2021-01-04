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

namespace Go\Core;

use Closure;
use OutOfBoundsException;

/**
 * DI-container
 */
abstract class Container implements AspectContainer
{
    /**
     * List of services in the container
     */
    protected array $values = [];

    /**
     * Store identifiers os services by tags
     */
    protected array $tags = [];

    /**
     * Set a service into the container
     *
     * @param mixed $value Value to store
     */
    public function set(string $id, $value, array $tags = []): void
    {
        $this->values[$id] = $value;
        foreach ($tags as $tag) {
            $this->tags[$tag][] = $id;
        }
    }

    /**
     * Set a shared value in the container
     */
    public function share(string $id, Closure $value, array $tags = []): void
    {
        $value = function ($container) use ($value) {
            static $sharedValue;

            if ($sharedValue === null) {
                $sharedValue = $value($container);
            }

            return $sharedValue;
        };
        $this->set($id, $value, $tags);
    }

    /**
     * Return a service or value from the container
     *
     * @return mixed
     * @throws OutOfBoundsException if service was not found
     */
    public function get(string $id)
    {
        if (!isset($this->values[$id])) {
            throw new OutOfBoundsException("Value {$id} is not defined in the container");
        }
        if ($this->values[$id] instanceof Closure) {
            return $this->values[$id]($this);
        }

        return $this->values[$id];
    }

    /**
     * Checks if item with specified id is present in the container
     */
    public function has(string $id): bool
    {
        return isset($this->values[$id]);
    }

    /**
     * Return list of service tagged with marker
     */
    public function getByTag(string $tag): array
    {
        $result = [];
        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $id) {
                $result[$id] = $this->get($id);
            }
        }

        return $result;
    }
}
