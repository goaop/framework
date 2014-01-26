<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
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
 