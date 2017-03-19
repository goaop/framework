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
use Go\Lang\Annotation\DeclareError;

/**
 * This aspect can be very useful for development to generate an error when executing prohibited methods
 */
class DeclareErrorAspect implements Aspect
{
    /**
     * Message to show when calling the method
     *
     * @var string
     * @DeclareError("@execution(Demo\Annotation\Deprecated)", level=16384) // E_USER_DEPRECATED
     */
    protected $message = 'Method is deprecated and should not be called in debug mode';

    /**
     * Prevent developers from using this method by always generating a warning
     *
     * @var string
     * @DeclareError("execution(public Demo\Example\ErrorDemo->notSoGoodMethod(*))", level=512) // E_USER_WARNING
     */
    protected $badMethod = 'Method can generate division by zero! Do not use it!';
}
