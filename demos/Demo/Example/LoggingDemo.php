<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Example;

use Demo\Annotation\Loggable;

/**
 * Example class to show how to use logging with AOP
 */
class LoggingDemo
{

    /**
     * Executes a task and logs all incoming arguments
     *
     * @Loggable
     * @param mixed $task Some specific argument
     */
    public function execute($task)
    {
        $this->perform($task, 'first');
        $this->perform($task, 'second');
    }

    /**
     * Protected method can be also loggable
     *
     * @Loggable
     *
     * @param mixed $task Specific task
     * @param string $level
     */
    protected function perform($task, $level)
    {
        // some logic here
    }

    /**
     * Everything is possible with AOP, so static methods can be intercepted too
     *
     * @Loggable
     *
     * @param string $task Some specific argument
     */
    public static function runByName($task)
    {
        $instance = new static(); // Go! AOP requires LSB to work correctly
        $instance->execute($task);
    }
}
