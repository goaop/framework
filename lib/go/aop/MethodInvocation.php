<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop;

/**
 * @package go
 * @subpackage aop
 */
interface MethodInvocation extends Invocation
{
    /**
     * Returns the method being called
     *
     * @return \ReflectionMethod
     */
    public function getMethod();
}
