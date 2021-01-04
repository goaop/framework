<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Aspect\Introduce;

/**
 * Example class to test aspects
 */
trait SerializableImpl
{
    /**
     * String representation of object
     *
     * @return string the string representation of the object or null
     */
    public function serialize(): string
    {
        return serialize(get_object_vars($this));
    }

    /**
     * Constructs the object
     *
     * @param string $serialized The string representation of the object.
     */
    public function unserialize($serialized): void
    {
        $data = unserialize($serialized);
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
