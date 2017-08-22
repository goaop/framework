<?php

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Lang\Annotation as Pointcut;

class InitializationAspect implements Aspect
{
    /**
     * Before class instance initialization.
     *
     * @Pointcut\Before("initialization(Go\Tests\TestProject\Application\Main)")
     */
    public function beforeInstanceInitialization()
    {
        echo 'It invokes before class instance is initialized.';
    }

    /**
     * After class initialization.
     *
     * @Pointcut\After("staticinitialization(Go\Tests\TestProject\Application\Main)")
     */
    public function afterClassStaticInitialization()
    {
        echo 'It invokes after class is loaded into memory.';
    }
}
