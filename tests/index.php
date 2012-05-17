<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

use Go\Core\Autoload;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\Transformer\AopProxyTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../src' . PATH_SEPARATOR . __DIR__ . '/../vendor/andrewsville/php-token-reflection');

ini_set('display_errors', true);

include '../src/Go/Core/Autoload.php';
Autoload::init();

/*********************************************************************************
 *                             ASPECT BLOCK
**********************************************************************************/

$pointcut = new \Go\Aop\Support\NameMatchMethodPointcut();
$pointcut->setMappedName('*el*');

$advice = new \Go\Aop\Framework\MethodBeforeInterceptor(function() {echo 'Hello';}, $pointcut);
$advisor = new \Go\Aop\Support\DefaultPointcutAdvisor($pointcut, $advice);

/*********************************************************************************
 *                             CONFIGURATION FOR TRANSFORMERS BLOCK
**********************************************************************************/
SourceTransformingLoader::registerFilter();

$sourceTransformers = array(
    new FilterInjectorTransformer(__DIR__, __DIR__, SourceTransformingLoader::getId()),
    new AopProxyTransformer(
        new TokenReflection\Broker(
            new TokenReflection\Broker\Backend\Memory()
        ),
        $advisor
    ),
);

foreach ($sourceTransformers as $sourceTransformer) {
    SourceTransformingLoader::addTransformer($sourceTransformer);
}

/*********************************************************************************
 *                             TEST CODE BLOCK
 * Remark: SourceTransformingLoader::load('app_autoload.php') should be here later
**********************************************************************************/

$class       = new Example();
$refClass    = new ReflectionClass($class);
$classFilter = $advisor->getPointcut()->getClassFilter();

if ($classFilter->matches($refClass)) {
    $pointFilter = $advisor->getPointcut()->getPointFilter();
    if ($pointFilter->matches($refClass->getMethod('hello'))) {

        $invocation = new \Go\Aop\Framework\ReflectionMethodInvocation($class, 'hello', array($advisor->getAdvice()));
        return $invocation('test');
    };
}
