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

/**
 * @deprecated since 1.0.0
 */
trait ReflectionProceedTrait
{
    /**
     * Invokes original method and return result from it
     *
     * @return mixed
     */
    public function proceed()
    {
        if (isset($this->advices[$this->current])) {
            /** @var $currentInterceptor \Go\Aop\Intercept\Interceptor */
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        // Due to bug https://bugs.php.net/bug.php?id=60968 instance shouldn't be a string
        $instance = ($this->instance !== (object) $this->instance) ? null : $this->instance;

        return $this->reflectionMethod->invokeArgs($instance, $this->arguments);
    }
}
