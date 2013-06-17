<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

include __DIR__ . (isset($_GET['original']) ? './autoload.php' : './autoload_aspect.php');

// Test case with human
$man = new Demo\Example\Human();
echo "Want to eat something, let's have a breakfast!", PHP_EOL;
$man->eat();
echo "I should work to earn some money", PHP_EOL;
$man->work();
echo "It was a nice day, go to bed", PHP_EOL;
$man->sleep();

// Test case for fluent interface
$user = new Demo\Example\User();
$user
    ->setName('test')
    ->setSurname('a')
    ->setPassword('root');
