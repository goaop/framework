<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Aspect;

use Go\Aop\Intercept\MethodInvocation;

/**
 * Debug aspect
 */
class DebugAspect
{
    /**
     * Message to show when calling the method
     *
     * @var string
     */
    protected $message = '';

    /**
     * Aspect constructor
     *
     * @param string $message Additional message to show
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Method that should be called before real method
     *
     * @param MethodInvocation $invocation Invocation
     */
    public function beforeMethodCall(MethodInvocation $invocation)
    {
        $method = $invocation->getMethod()->getName();
        $args   = json_encode($invocation->getArguments());
        echo "Calling {$method} with {$args}", "<br>\n";
        echo $this->message, "<br>\n";
    }
}
