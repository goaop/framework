<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Example;

use InvalidArgumentException;
use Demo\Aspect\FluentInterface;

/**
 * Example class to show fluent interface in action
 */
class UserFluentDemo implements FluentInterface
{
    protected ?string $name = null;
    protected ?string $surname = null;
    protected ?string $password = null;

    public function setName(string $name)
    {
        echo "Set user name to ", $name, PHP_EOL;
        $this->name = $name;
    }

    public function setSurname(string $surname)
    {
        echo "Set user surname to ", $surname, PHP_EOL;
        $this->surname = $surname;
    }

    public function setPassword(string $password)
    {
        if ($password === '') {
            throw new InvalidArgumentException("Password shouldn't be empty");
        }
        echo "Set user password to ", $password, PHP_EOL;
        $this->password = $password;
    }
}
