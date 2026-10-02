<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2019, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Core\AspectContainer;

class ReflectionConstructorInvocationTest extends AbstractInterceptorTestCase
{
    public function testCanCreateObjectDuringInvocation(): void
    {
        $invocation = new ReflectionConstructorInvocation([], \Exception::class);
        $result     = $invocation->__invoke();
        $this->assertSame(\Exception::class, $result::class);
    }

    public function testCanExecuteAdvicesDuringConstruct(): void
    {
        $sequence   = [];
        $advice     = $this->getAdvice($sequence);
        $before     = new BeforeInterceptor($advice);
        $invocation = new ReflectionConstructorInvocation([$before], \Exception::class);
        $this->assertEmpty($sequence);
        $invocation->__invoke(['Message', 100]);
        $this->assertContains('advice', $sequence);
    }

    public function testStringRepresentation(): void
    {
        $invocation = new ReflectionConstructorInvocation([], \Exception::class);
        $name       = (string) $invocation;

        $this->assertEquals('initialization(Exception)', $name);
    }

    public function testReturnsConstructor(): void
    {
        $invocation = new ReflectionConstructorInvocation([], \Exception::class);
        $ctor       = $invocation->getConstructor();
        $this->assertInstanceOf(\ReflectionMethod::class, $ctor);
        $this->assertEquals('__construct', $ctor->name);
    }

    public function testReturnsThis(): void
    {
        $observed   = [];
        $around     = new AroundInterceptor(static function (ReflectionConstructorInvocation $invocation) use (&$observed): object {
            $observed['before'] = $invocation->getThis();
            $object             = $invocation->proceed();
            $observed['after']  = $invocation->getThis();

            return $object;
        });
        $invocation = new ReflectionConstructorInvocation([$around], \Exception::class);
        $this->assertNull($invocation->getThis());

        $object = $invocation->__invoke(['Some error', 100]);

        $this->assertNull($observed['before']);
        $this->assertSame($object, $observed['after']);
        // The shared invocation does not keep the created object after the call
        $this->assertNull($invocation->getThis());
    }

    public function testBeforeAdviceSeesNoInstanceOnRepeatedConstruction(): void
    {
        $observed   = [];
        $before     = new BeforeInterceptor(static function (ReflectionConstructorInvocation $invocation) use (&$observed): void {
            $observed[] = $invocation->getThis();
        });
        $invocation = new ReflectionConstructorInvocation([$before], \Exception::class);

        $invocation->__invoke(['first']);
        $invocation->__invoke(['second']);

        $this->assertSame([null, null], $observed);
    }

    public function testInvocationDoesNotRetainCreatedInstance(): void
    {
        $invocation = new ReflectionConstructorInvocation([], \ArrayObject::class);
        $reference  = \WeakReference::create($invocation->__invoke([[1, 2, 3]]));

        $this->assertNull($reference->get());
    }

    public function testNestedConstructionRestoresOuterState(): void
    {
        $observed   = [];
        $around     = new AroundInterceptor(static function (ReflectionConstructorInvocation $invocation) use (&$observed): object {
            $object = $invocation->proceed();
            // The nested construction of the same class has completed inside proceed()
            $observed[] = [$invocation->getArguments(), $invocation->getThis() === $object];

            return $object;
        });
        $invocation = new ReflectionConstructorInvocation([$around], NestedConstructionFixture::class);
        NestedConstructionFixture::$invocation = $invocation;

        $outer = $invocation->__invoke([1]);

        $this->assertSame(1, $outer->depth);
        $this->assertInstanceOf(NestedConstructionFixture::class, $outer->child);
        $this->assertSame(0, $outer->child->depth);
        $this->assertSame([[[0], true], [[1], true]], $observed);
    }

    public function testCanCreateAnInstanceEvenWithNonPublicConstructor(): void
    {
        try {
            // @phpstan-ignore new.privateConstructor (instantiation failure of a private constructor is the test subject)
            $testClassInstance = new class ('Test') {
                public string $message;

                private function __construct(string $message)
                {
                    $this->message = $message;
                }
            };
            $loadedClass = get_class($testClassInstance);
        } catch (\Error $e) {
            // let's look for all class names to find our anonymous one
            foreach (get_declared_classes() as $loadedClass) {
                $refClass = new \ReflectionClass($loadedClass);
                if ($refClass->getFileName() === __FILE__ && strpos($refClass->getName(), 'anonymous') !== false) {
                    // loadedClass will contain our anonymous class
                    break;
                }
            }
        }
        assert(isset($loadedClass));
        $testClassName = $loadedClass;
        $invocation    = new ReflectionConstructorInvocation([], $testClassName);
        $result        = $invocation->__invoke(['Hello']);
        $this->assertInstanceOf($testClassName, $result);
        // @phpstan-ignore property.notFound (the anonymous class is only known at runtime)
        $this->assertSame('Hello', $result->message);
    }
}

/**
 * Creates a child of the same class through the shared constructor invocation from its own constructor
 */
class NestedConstructionFixture
{
    /**
     * @var ReflectionConstructorInvocation<NestedConstructionFixture>
     */
    public static ReflectionConstructorInvocation $invocation;

    public ?self $child = null;

    public function __construct(public readonly int $depth)
    {
        if ($depth > 0) {
            $this->child = self::$invocation->__invoke([$depth - 1]);
        }
    }
}
