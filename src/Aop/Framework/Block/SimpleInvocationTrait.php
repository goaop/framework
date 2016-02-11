<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework\Block;


trait SimpleInvocationTrait
{

    /**
     * Invokes current method invocation with all interceptors
     *
     * @param null|object|string $instance Invocation instance (class name for static methods)
     * @param array $arguments List of arguments for method invocation
     *
     * @return mixed Result of invocation
     */
    final public function __invoke($instance = null, array $arguments = [])
    {
        if ($this->level) {
            array_push($this->stackFrames, array($this->arguments, $this->instance, $this->current));
        }

        ++$this->level;

        $this->current   = 0;
        $this->instance  = $instance;
        $this->arguments = $arguments;

        $result = $this->proceed();

        --$this->level;

        if ($this->level) {
            list($this->arguments, $this->instance, $this->current) = array_pop($this->stackFrames);
        }

        return $result;
    }
}
