<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FunctionInvocation;
use Go\Lang\Annotation\Around;

/**
 * Function interceptor can intercept an access to the system functions
 */
class FunctionInterceptorAspect implements Aspect
{

    /**
     * This advice intercepts an access to the array_*** function in Demo\Example\ namespace
     *
     * @param FunctionInvocation $invocation
     *
     * @Around("execution(Demo\Example\array_*(*))")
     *
     * @return mixed
     */
    public function aroundArrayFunctions(FunctionInvocation $invocation)
    {
        echo 'Calling Around Interceptor for ',
            $invocation,
            ' with arguments: ',
            json_encode($invocation->getArguments()),
            PHP_EOL;

        return $invocation->proceed();
    }

    /**
     * This advice intercepts an access to the file_get_contents() function
     *
     * @param FunctionInvocation $invocation
     *
     * @Around("execution(Demo\Example\file_get_contents(*))")
     *
     * @return mixed
     */
    public function aroundFileGetContents(FunctionInvocation $invocation)
    {
        echo 'Calling Around Interceptor for ',
            $invocation,
            ' with arguments: ',
            json_encode($invocation->getArguments()),
            PHP_EOL;

        // return $invocation->proceed(); // Do not call original file_get_contents()
        return 'Hello!'; // Override return value for function
    }
}
