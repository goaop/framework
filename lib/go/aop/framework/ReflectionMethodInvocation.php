<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop\framework;

/**
 * @package go
 */
class ReflectionMethodInvocation extends AbstractMethodInvocation
{
    /**
     * Invokes original method and return result from it
     *
     * @return mixed
     */
    protected function invokeOriginalMethod()
    {
        return $this->getMethod()->invokeArgs($this->instance, $this->arguments);
    }
}
