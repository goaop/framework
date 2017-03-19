<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
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
     * @Pointcut("execution(public Demo\Example\HumanDemo->eat(*))")
     */
    protected function humanEat() {}

    /**
     * Washing hands before eating
     *
     * @param MethodInvocation $invocation Invocation
     * @Before("$this->humanEat")
     */
    protected function washUpBeforeEat(MethodInvocation $invocation)
    {
        /** @var $person \Demo\Example\HumanDemo */
        $person = $invocation->getThis();
        $person->washUp();
    }

    /**
     * Method that advices to clean the teeth after eating
     *
     * @param MethodInvocation $invocation Invocation
     * @After("$this->humanEat")
     */
    protected function cleanTeethAfterEat(MethodInvocation $invocation)
    {
        /** @var $person \Demo\Example\HumanDemo */
        $person = $invocation->getThis();
        $person->cleanTeeth();
    }

    /**
     * Method that advice to clean the teeth before going to sleep
     *
     * @param MethodInvocation $invocation Invocation
     * @Before("execution(public Demo\Example\HumanDemo->sleep(*))")
     */
    protected function cleanTeethBeforeSleep(MethodInvocation $invocation)
    {
        /** @var $person \Demo\Example\HumanDemo */
        $person = $invocation->getThis();
        $person->cleanTeeth();
    }
}
