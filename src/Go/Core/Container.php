<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
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
    protected $values = array();

    /**
     * Store identifiers os services by tags
     *
     * @var array
     */
    protected $tags = array();

    /**
     * Set a service into the container
     *
     * @param string $id Identifier
     * @param mixed $value Value to store
     * @param array $tags Additional tags
     */
    public function set($id, $value, array $tags = array())
    {
        $this->values[$id] = $value;
        foreach ($tags as $tag) {
            $this->tags[$tag][] = $id;
        }
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
     * Return list of service tagged with marker
     *
     * @param string $tag Tag to select
     * @return array
     */
    public function getByTag($tag)
    {
        $result = array();
        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $id) {
                $result[$id] = $this->get($id);
            }
        }
        return $result;
    }
}
