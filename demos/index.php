<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

use Go\Aop\Support\AdvisorRegistry;
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

include '../src/Go/Core/AbstractAspectKernel.php';
include 'DemoAspectKernel.php';

DemoAspectKernel::getInstance()->init();

/**
 * Temporary function to return closure from aspect
 *
 * @param object $aspect Aspect instance
 * @param string $methodName Method name for callback
 *
 * @return closure
 */
function getCallback($aspect, $methodName)
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

/*********************************************************************************
 *                             ASPECT BLOCK
**********************************************************************************/

$pointcut = new NameMatchMethodPointcut();
$pointcut->setMappedName('*');

$aspect        = new Aspect\DebugAspect('ASPECT!');

// Register before advisor
$before        = new MethodBeforeInterceptor(getCallback($aspect, 'beforeMethodExecution'));
$beforeAdvisor = new DefaultPointcutAdvisor($pointcut, $before);
AdvisorRegistry::register($beforeAdvisor);

// Register after advisor
$after        = new MethodAfterInterceptor(getCallback($aspect, 'afterMethodExecution'));
$afterAdvisor = new DefaultPointcutAdvisor($pointcut, $after);
AdvisorRegistry::register($afterAdvisor);

// Register around field advisor
$fieldPointcut = new NameMatchPropertyPointcut();
$fieldPointcut->setMappedName('*');
$fieldAdvice  = new FieldAroundInterceptor(getCallback($aspect, 'aroundFieldAccess'));
$fieldAdvisor = new DefaultPointcutAdvisor($fieldPointcut, $fieldAdvice);
AdvisorRegistry::register($fieldAdvisor);

/*********************************************************************************
 *                             TEST CODE BLOCK
 * Remark: SourceTransformingLoader::load('app_autoload.php') should be here later
**********************************************************************************/

$class = new Example('test');
$class->publicHello();

echo "=========================================<br>\n";
