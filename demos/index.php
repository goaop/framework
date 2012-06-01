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
use Go\Aop\Framework\FieldBeforeInterceptor;
use Go\Aop\Framework\ClassFieldAccess;
use Go\Aop\Framework\MethodAfterInterceptor;
use Go\Aop\Framework\MethodBeforeInterceptor;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\MethodInvocation;

include 'aspect_loader.php';

/*********************************************************************************
 *                             ASPECT BLOCK
**********************************************************************************/

$pointcut = new NameMatchMethodPointcut();
$pointcut->setMappedName('*');

$before = new MethodBeforeInterceptor(function(MethodInvocation $invocation) {
    $obj = $invocation->getThis();
    echo 'Calling Before Interceptor for method: ',
         is_object($obj) ? get_class($obj) : $obj,
         $invocation->getMethod()->isStatic() ? '::' : '->',
         $invocation->getMethod()->getName(),
         '()',
         ' with arguments: ',
         json_encode($invocation->getArguments()),
         "<br>\n";
}, $pointcut);

$after = new MethodAfterInterceptor(function(MethodInvocation $invocation) {
    $obj = $invocation->getThis();
    echo 'Calling After Interceptor for method: ',
         is_object($obj) ? get_class($obj) : $obj,
         $invocation->getMethod()->isStatic() ? '::' : '->',
         $invocation->getMethod()->getName(),
         '()',
         ' with arguments: ',
         json_encode($invocation->getArguments()),
         "<br>\n";
}, $pointcut);

$fieldPointcut = new \Go\Aop\Support\NameMatchPropertyPointcut();
$fieldPointcut->setMappedName('*');
$fieldAdvice = new \Go\Aop\Framework\FieldAroundInterceptor(function (FieldAccess $property) {
    $type = $property->getAccessType() === FieldAccess::READ ? 'read' : 'write';
    $value = $property->proceed();
    echo
        "Calling Around Interceptor for field: ",
        get_class($property->getThis()),
        "->",
        $property->getField()->getName(),
        ", access: $type",
        ", value: ",
        json_encode($value),
        "<br>\n";

    // Let's have a fun and change the value of property :))
    if ($property->getAccessType() === FieldAccess::WRITE) {
        return 'WRITE';
    }
    return $value;
});


$beforeAdvisor = new DefaultPointcutAdvisor($pointcut, $before);
$afterAdvisor  = new DefaultPointcutAdvisor($pointcut, $after);
$fieldAdvisor = new DefaultPointcutAdvisor($fieldPointcut, $fieldAdvice);

AdvisorRegistry::register($beforeAdvisor);
AdvisorRegistry::register($afterAdvisor);
AdvisorRegistry::register($fieldAdvisor);

/*********************************************************************************
 *                             TEST CODE BLOCK
 * Remark: SourceTransformingLoader::load('app_autoload.php') should be here later
**********************************************************************************/

$class = new Example('test');
$class->publicHello();

echo "=========================================<br>\n";
