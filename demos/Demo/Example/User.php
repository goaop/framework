<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
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
