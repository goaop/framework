<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

use Go\Core\AspectKernel;
use Go\Core\AspectContainer;

use Go\Aop\Support\DefaultPointcutAdvisor;
use Go\Aop\Support\NameMatchMethodPointcut;
use Go\Aop\Support\NameMatchPropertyPointcut;
use Go\Aop\Framework\FieldAroundInterceptor;
use Go\Aop\Framework\FieldBeforeInterceptor;
use Go\Aop\Framework\ClassFieldAccess;
use Go\Aop\Framework\MethodAfterInterceptor;
use Go\Aop\Framework\MethodBeforeInterceptor;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\MethodInvocation;

/**
 * Demo Aspect Kernel class
 */
class DemoAspectKernel extends AspectKernel
{

    /**
     * Returns the path to the application autoloader file, typical autoload.php
     *
     * @return string
     */
    protected function getApplicationLoaderPath()
    {
        return './autoload.php';
    }

    /**
     * Configure an AspectContainer with advisors, aspects and pointcuts
     *
     * @param AspectContainer $container
     *
     * @return void
     */
    protected function configureAop(AspectContainer $container)
    {
        $pointcut = new NameMatchMethodPointcut();
        $pointcut->setMappedName('*');

        $aspect = new Aspect\DebugAspect('ASPECT!');

        // Register before advisor
        $before        = new MethodBeforeInterceptor($this->getCallback($aspect, 'beforeMethodExecution'));
        $beforeAdvisor = new DefaultPointcutAdvisor($pointcut, $before);
        $container->registerAdvisor($beforeAdvisor);

        // Register after advisor
        $after        = new MethodAfterInterceptor($this->getCallback($aspect, 'afterMethodExecution'));
        $afterAdvisor = new DefaultPointcutAdvisor($pointcut, $after);
        $container->registerAdvisor($afterAdvisor);

        // Register around field advisor
        $fieldPointcut = new NameMatchPropertyPointcut();
        $fieldPointcut->setMappedName('*');
        $fieldAdvice  = new FieldAroundInterceptor($this->getCallback($aspect, 'aroundFieldAccess'));
        $fieldAdvisor = new DefaultPointcutAdvisor($fieldPointcut, $fieldAdvice);
        $container->registerAdvisor($fieldAdvisor);
    }


    /**
     * Temporary function to return closure from aspect
     *
     * @param object $aspect Aspect instance
     * @param string $methodName Method name for callback
     *
     * @return closure
     */
    private function getCallback($aspect, $methodName)
    {
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $refClass = new ReflectionClass($aspect);
            return $refClass->getMethod($methodName)->getClosure($aspect);
        } else {
            return function () use ($aspect, $methodName) {
                return call_user_func_array(array($aspect, $methodName), func_get_args());
            };
        }
    }
}