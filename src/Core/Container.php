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

/**
 * DI-container
 */
class Container
{
    /**
     * List of services in the container
     *
     * @var array
     */
    protected $values = [];

    /**
     * Store identifiers os services by tags
     *
     * @var array
     */
    protected $tags = [];

    /**
     * Set a service into the container
     *
     * @param string $id Identifier
     * @param mixed $value Value to store
     * @param array $tags Additional tags
     */
    public function set($id, $value, array $tags = [])
    {
        $this->values[$id] = $value;
        foreach ($tags as $tag) {
            $this->tags[$tag][] = $id;
        }
    }

    /**
     * Set a shared value in the container
     *
     * @param string $id Identifier
     * @param callable $value Value to store
     * @param array $tags Additional tags
     *
     * @throws \InvalidArgumentException if value is not callable
     */
    public function share($id, $value, array $tags = [])
    {
        if (!is_callable($value)) {
            throw new \InvalidArgumentException("Only callable values can be shared in the container");
        }
        $value = function($container) use ($value) {
            static $sharedValue;

            if (null === $sharedValue) {
                $sharedValue = $value($container);
            }

            return $sharedValue;
        };
        $this->set($id, $value, $tags);
    }

    /**
     * Return a service or value from the container
     *
     * @param string $id Identifier
     *
     * @return mixed
     * @throws \OutOfBoundsException if service was not found
     */
    public function get($id)
    {
        if (!isset($this->values[$id])) {
            throw new \OutOfBoundsException("Value {$id} is not defined in the container");
        }
        if (is_callable($this->values[$id])) {
            return $this->values[$id]($this);
        } else {
            return $this->values[$id];
        }
    }

    /**
     * Checks if item with specified id is present in the container
     *
     * @param string $id Identifier
     *
     * @return bool
     */
    public function has($id)
    {
        return isset($this->values[$id]);
    }

    /**
     * Return list of service tagged with marker
     *
     * @param string $tag Tag to select
     * @return array
     */
    public function getByTag($tag)
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
