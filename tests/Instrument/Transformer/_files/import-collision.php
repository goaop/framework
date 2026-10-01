<?php
declare(strict_types=1);
namespace Test\ns1;

use Go\Stubs\Collision\Interceptor;
use Go\Stubs\Collision\The;
use Go\Aop\Framework\InterceptorInjector as Injector;

/**
 * Imports bind the short names used by generated proxies (Interceptor, The) to unrelated
 * classes, and alias a framework class: the proxy must neither redeclare nor rely on them
 */
class ImportCollisionClass
{
    public function log(string $level = Interceptor::LEVEL, ?The $target = null): string
    {
        return $level;
    }

    public function injector(): ?Injector
    {
        return null;
    }
}
