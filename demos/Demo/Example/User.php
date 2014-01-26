<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Example;

use Demo\Aspect\FluentInterface;

/**
 * Example class to show fluent interface in action
 */
class User implements FluentInterface
{
    protected $name;
    protected $surname;
    protected $password;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setSurname($surname)
    {
        $this->surname = $surname;
    }

    public function setPassword($password)
    {
        if (!$password) {
            throw new \InvalidArgumentException("Password shouldn't be empty");
        }
        $this->password = $password;
    }
}
