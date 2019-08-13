<?php
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

class ReflectionConstructorInvocationTest extends AbstractInterceptorTest
{
    public function testCanCreateObjectDuringInvocation()
    {
        $invocation = new ReflectionConstructorInvocation(\Exception::class, 'unused', []);
        $result     = $invocation->__invoke();
        $this->assertInstanceOf(\Exception::class, $result);
    }

    public function testKnowsAboutSpecialClassSuffix()
    {
        $specialName = \Exception::class . AspectContainer::AOP_PROXIED_SUFFIX;
        $invocation  = new ReflectionConstructorInvocation($specialName, 'unused', []);
        $result      = $invocation->__invoke();
        $this->assertInstanceOf(\Exception::class, $result);
    }

    public function testCanExecuteAdvicesDuringConstruct()
    {
        $sequence   = [];
        $advice     = $this->getAdvice($sequence);
        $before     = new BeforeInterceptor($advice);
        $invocation = new ReflectionConstructorInvocation(\Exception::class, 'unused', [$before]);
        $this->assertEmpty($sequence);
        $invocation->__invoke(['Message', 100]);
        $this->assertContains('advice', $sequence);
    }

    public function testStringRepresentation()
    {
        $invocation = new ReflectionConstructorInvocation(\Exception::class, 'unused', []);
        $name       = (string)$invocation;

        $this->assertEquals('initialization(Exception)', $name);
    }

    public function testReturnsConstructor()
    {
        $invocation = new ReflectionConstructorInvocation(\Exception::class, 'unused', []);
        $ctor       = $invocation->getConstructor();
        $this->assertInstanceOf(\ReflectionMethod::class, $ctor);
        $this->assertEquals('__construct', $ctor->name);
    }

    public function testReturnsThis()
    {
        $invocation = new ReflectionConstructorInvocation(\Exception::class, 'unused', []);
        $instance   = $invocation->getThis();
        $this->assertNull($instance);
        $object = $invocation->__invoke(['Some error', 100]);
        $this->assertEquals($object, $invocation->getThis());
    }

    public function testCanCreateAnInstanceEvenWithNonPublicConstructor()
    {
        try {
            $testClassInstance = new class('Test') {
                public $message;

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
        $testClassName = $loadedClass;
        $invocation    = new ReflectionConstructorInvocation($testClassName, 'unused', []);
        $result        = $invocation->__invoke(['Hello']);
        $this->assertInstanceOf($testClassName, $result);
        $this->assertSame('Hello', $result->message);
    }
}
