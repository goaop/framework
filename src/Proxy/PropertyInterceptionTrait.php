<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy;

use Go\Aop\Framework\ClassFieldAccess;

/**
 * Trait that holds all general logic for working with intercepted properties
 */
trait PropertyInterceptionTrait
{
    /**
     * Holds a collection of current values for intercepted properties
     *
     * @var array
     */
    private $__properties = [];

    /**
     * @inheritDoc
     */
    public function &__get($name)
    {
        if (\array_key_exists($name, $this->__properties)) {
            /** @var ClassFieldAccess $fieldAccess */
            $fieldAccess = self::$__joinPoints["prop:$name"];
            $fieldAccess->ensureScopeRule();

            $value = &$fieldAccess->__invoke($this, ClassFieldAccess::READ, $this->__properties[$name]);
        } elseif (\method_exists(\get_parent_class(), __FUNCTION__)) {
            $value = parent::__get($name);
        } else {
            trigger_error("Trying to access undeclared property {$name}");

            $value = null;
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function __set($name, $value)
    {
        if (\array_key_exists($name, $this->__properties)) {
            /** @var ClassFieldAccess $fieldAccess */
            $fieldAccess = self::$__joinPoints["prop:$name"];
            $fieldAccess->ensureScopeRule();

            $this->__properties[$name] = $fieldAccess->__invoke(
                $this,
                ClassFieldAccess::WRITE,
                $this->__properties[$name],
                $value
            );
        } elseif (\method_exists(\get_parent_class(), __FUNCTION__)) {
            parent::__set($name, $value);
        } else {
            $this->$name = $value;
        }
    }

    /**
     * @inheritDoc
     */
    public function __isset($name)
    {
        return isset($this->__properties[$name]);
    }

    /**
     * @inheritDoc
     */
    public function __unset($name)
    {
        $this->__properties[$name] = null;
    }
}
