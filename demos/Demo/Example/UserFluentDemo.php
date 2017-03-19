<?php
declare(strict_types = 1);
/*
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
class UserFluentDemo implements FluentInterface
{
    protected $name;
    protected $surname;
    protected $password;

    public function setName($name)
    {
        echo "Set user name to ", $name, PHP_EOL;
        $this->name = $name;
    }

    public function setSurname($surname)
    {
        echo "Set user surname to ", $surname, PHP_EOL;
        $this->surname = $surname;
    }

    public function setPassword($password)
    {
        if (!$password) {
            throw new \InvalidArgumentException("Password shouldn't be empty");
        }
        echo "Set user password to ", $password, PHP_EOL;
        $this->password = $password;
    }
}
