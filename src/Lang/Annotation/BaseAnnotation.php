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

namespace Go\Lang\Annotation;

/**
 * @Annotation
 */
abstract class BaseAnnotation
{
    /**
     * Value property. Common among all derived classes.
     *
     * @var string
     */
    public $value;

    /**
     * Constructor
     *
     * @param array $data Key-value for properties to be defined in this class
     */
    final public function __construct(array $data)
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Error handler for unknown property accessor in Annotation class.
     *
     * @param string $name Unknown property name
     */
    public function __get($name)
    {
        throw new \BadMethodCallException(
            sprintf("Unknown property '%s' on annotation '%s'.", $name, get_class($this))
        );
    }

    /**
     * Error handler for unknown property mutator in Annotation class.
     *
     * @param string $name Unknown property name
     * @param mixed $value Property value
     */
    public function __set($name, $value)
    {
        throw new \BadMethodCallException(
            sprintf("Unknown property '%s' on annotation '%s'.", $name, get_class($this))
        );
    }
}
