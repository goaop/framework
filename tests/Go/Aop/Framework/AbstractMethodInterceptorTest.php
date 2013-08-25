<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

/**
 * @method \Go\Aop\Intercept\MethodInvocation getInvocation(&$sequenceRecorder, $throwException = false)
 */
abstract class AbstractMethodInterceptorTest extends AbstractInterceptorTest
{
    /**
     * Override the default class for method tests
     */
    const INVOCATION_CLASS = 'Go\Aop\Intercept\MethodInvocation';
}
 