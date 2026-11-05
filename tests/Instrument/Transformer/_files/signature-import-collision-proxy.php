<?php
declare(strict_types=1);
namespace Go\Instrument\Transformer\Stubs;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The as AopThe;
use Go\Aop\Intercept\DynamicMethodInvocation;
use Go\Aop\Intercept\FieldAccess;
use App\Attribute\Loggable;
use Go\Aop\Proxy as AopProxy;
use App\Domain\Money as DomainMoney;
use App\Domain\Product as DomainProduct;
use Countable;
use Traversable;
use Exception as GlobalException;
use Go\Stubs\Collision\Interceptor as Product;
use Go\Stubs\Collision\The as The;
/**
 * Signature types colliding with the original imports and with the short names of generated references (#826):
 * App\Domain\Product with the imported Product alias, the global Exception and App\Domain\Money with classes of this
 * namespace, the local Proxy class with Go\Aop\Proxy
 */
#[Loggable]
class SignatureCollisionClass implements AopProxy
{
    use SignatureCollisionClassOriginalTrait {
        SignatureCollisionClassOriginalTrait::priceFor as private priceForOriginalAlias;
        SignatureCollisionClassOriginalTrait::combine as private combineOriginalAlias;
    }
    public ?Proxy $proxy = null {
        get {
            /** @var FieldAccess<self, ?Proxy> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forProperty(
                self::class,
                'proxy',
                [
                    Interceptor::before(AopThe::advice('advisor.Go\Instrument\Transformer\Stubs\SignatureCollisionClass->proxy')),
                ],
            );
            return $__joinPoint->read($this, $this->proxy);
        }
        set {
            /** @var FieldAccess<self, ?Proxy> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forProperty(
                self::class,
                'proxy',
                [
                    Interceptor::before(AopThe::advice('advisor.Go\Instrument\Transformer\Stubs\SignatureCollisionClass->proxy')),
                ],
            );
            $this->proxy = $__joinPoint->write($this, $value, $this->proxy);
        }
    }
    public function priceFor(DomainProduct $product, Product $imported, string $level = The::LEVEL): DomainMoney|Money
    {
        /** @var DynamicMethodInvocation<self, DomainMoney|Money> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'priceFor',
            [
                Interceptor::before(AopThe::advice('advisor.Go\Instrument\Transformer\Stubs\SignatureCollisionClass->priceFor')),
            ],
            $this->priceForOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$product, $imported, $level], 0, \func_num_args()));
    }
    #[Loggable(DomainProduct::class, level: Product::LEVEL)]
    public function combine((Countable&Traversable)|DomainProduct|null $items, ?self $same = null, GlobalException|Exception ...$errors): static
    {
        /** @var DynamicMethodInvocation<self, static> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'combine',
            [
                Interceptor::before(AopThe::advice('advisor.Go\Instrument\Transformer\Stubs\SignatureCollisionClass->combine')),
            ],
            $this->combineOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$items, $same], 0, \func_num_args()), $errors);
    }
}