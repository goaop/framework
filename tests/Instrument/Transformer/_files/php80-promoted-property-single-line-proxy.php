<?php
declare(strict_types=1);
namespace Go\Tests\TestProject\Application;

use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Intercept\DynamicMethodInvocation;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldAccessType;
/**
 * Class with a promoted constructor property in a single-line constructor used for
 * testing interception of promoted properties (issue #599).
 */
class SingleLinePromotedClass implements \Go\Aop\Proxy
{
    use SingleLinePromotedClass__AopProxied {
        SingleLinePromotedClass__AopProxied::__construct as private __aop____construct;
    }
    public string $tag = 'default' {
        get {
            /** @var FieldAccess<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forProperty(self::class, 'tag', ['advisor.Go\Tests\TestProject\Application\SingleLinePromotedClass->tag']);
            return $__joinPoint->__invoke($this, FieldAccessType::READ, $this->tag);
        }
        set {
            /** @var FieldAccess<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forProperty(self::class, 'tag', ['advisor.Go\Tests\TestProject\Application\SingleLinePromotedClass->tag']);
            $this->tag = $__joinPoint->__invoke($this, FieldAccessType::WRITE, $value, $this->tag);
        }
    }
    public function __construct(string $tag = 'default')
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, '__construct', ['advisor.Go\Tests\TestProject\Application\SingleLinePromotedClass->__construct'], $this->__aop____construct(...));
        return $__joinPoint->__invoke($this, \array_slice([$tag], 0, \func_num_args()));
    }
}