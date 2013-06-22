<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Demo\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\After;
use Go\Lang\Annotation\Before;
use Go\Lang\Annotation\Pointcut;

/**
 * Healthy live aspect
 */
class HealthyLiveAspect implements Aspect
{
    /**
     * Pointcut for eat method
     *
     * @Pointcut("execution(public Demo\Example\Human->eat(*))")
     */
    protected function humanEat() {}

    /**
     * Washing hands before eating
     *
     * @param MethodInvocation $invocation Invocation
     * @Before("Demo\Aspect\HealthyLiveAspect->humanEat")
     */
    protected function washUpBeforeEat(MethodInvocation $invocation)
    {
        /** @var $person \Demo\Example\Human */
        $person = $invocation->getThis();
        $person->washUp();
    }

    /**
     * Method that advices to clean the teeth after eating
     *
     * @param MethodInvocation $invocation Invocation
     * @After("Demo\Aspect\HealthyLiveAspect->humanEat")
     */
    protected function cleanTeethAfterEat(MethodInvocation $invocation)
    {
        /** @var $person \Demo\Example\Human */
        $person = $invocation->getThis();
        $person->cleanTeeth();
    }

    /**
     * Method that advice to clean the teeth before going to sleep
     *
     * @param MethodInvocation $invocation Invocation
     * @Before("execution(public Demo\Example\Human->sleep(*))")
     */
    protected function cleanTeethBeforeSleep(MethodInvocation $invocation)
    {
        /** @var $person \Demo\Example\Human */
        $person = $invocation->getThis();
        $person->cleanTeeth();
    }
}
