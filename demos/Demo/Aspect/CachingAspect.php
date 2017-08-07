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
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Around;

/**
 * Caching aspect
 */
class CachingAspect implements Aspect
{
    /**
     * This advice intercepts an execution of cacheable methods
     *
     * Logic is pretty simple: we look for the value in the cache and if it's not present here
     * then invoke original method and store it's result in the cache.
     *
     * Real-life examples will use APC or Memcache to store value in the cache
     *
     * @param MethodInvocation $invocation Invocation
     *
     * @Around("@execution(Demo\Annotation\Cacheable)")
     */
    public function aroundCacheable(MethodInvocation $invocation)
    {
        static $memoryCache = [];

        $time  = microtime(true);

        $obj   = $invocation->getThis();
        $class = is_object($obj) ? get_class($obj) : $obj;
        $key   = $class . ':' . $invocation->getMethod()->name;
        if (!isset($memoryCache[$key])) {
            // We can use ttl value from annotation, but Doctrine annotations doesn't work under GAE
            if (!isset($_SERVER['APPENGINE_RUNTIME'])) {
                echo "Ttl is: ", $invocation->getMethod()->getAnnotation('Demo\Annotation\Cacheable')->time, PHP_EOL;
            }

            $memoryCache[$key] = $invocation->proceed();
        }

        echo "Take ", sprintf("%0.3f", (microtime(true) - $time) * 1e3), "ms to call method $key", PHP_EOL;

        return $memoryCache[$key];
    }
}
