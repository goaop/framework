<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Example;

use Demo\Attribute\Loggable;

/**
 * Example class to show how to use logging with AOP
 */
class LoggingDemo
{

    /**
     * Executes a task and logs all incoming arguments
     *
     * @param string $task Some specific argument
     */
    #[Loggable]
    public function execute(string $task): void
    {
        $this->perform($task, 'first');
        $this->perform($task, 'second');
    }

    /**
     * Protected method can be also loggable
     *
     * @param string $task Specific task
     * @param string $level
     */
    #[Loggable]
    protected function perform(string $task, string $level): void
    {
        // some logic here
    }

    /**
     * Everything is possible with AOP, so static methods can be intercepted too
     *
     * @param string $task Some specific argument
     */
    #[Loggable]
    public static function runByName(string $task): void
    {
        $instance = new static(); // Go! AOP requires LSB to work correctly
        $instance->execute($task);
    }
}
