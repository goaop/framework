<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Lang\Attribute as Pointcut;

class Issue293Aspect implements Aspect
{
    /**
     * Intercepts only public/protected static methods
     */
    #[Pointcut\After("execution(public|protected Go\Tests\TestProject\Application\Issue293*::*(*))")]
    public function afterPublicOrProtectedStaticMethods()
    {
        echo 'It invokes only after static methods, not for dynamics.';
    }
}
