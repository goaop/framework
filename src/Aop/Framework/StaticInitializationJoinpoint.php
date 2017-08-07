<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Core\AspectContainer;
use ReflectionClass;

/**
 * Static initialization joinpoint is invoked after class is loaded into memory
 */
class StaticInitializationJoinpoint extends AbstractJoinpoint
{

    /**
     * @var ReflectionClass
     */
    protected $reflectionClass;

    /**
     * Constructor for static initialization joinpoint
     *
     * @param string $className Name of the class
     * @param string $type Type of joinpoint
     * @param $advices array List of advices for this invocation
     *
     * @internal param ReflectionClass $reflectionClass Reflection of class
     */
    public function __construct(string $className, string $type, array $advices)
    {
        if (strpos($className, AspectContainer::AOP_PROXIED_SUFFIX)) {
            $originalClass = substr($className, 0, -strlen(AspectContainer::AOP_PROXIED_SUFFIX));
        } else {
            $originalClass = $className;
        }
        $this->reflectionClass = new \ReflectionClass($originalClass);
        parent::__construct($advices);
    }

    /**
     * Proceeds to the next interceptor in the chain.
     *
     * @return mixed see the children interfaces' proceed definition.
     */
    public function proceed()
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];
            $currentInterceptor->invoke($this);
        }
    }

    /**
     * Invokes current joinpoint with all interceptors
     *
     * @return mixed
     */
    final public function __invoke()
    {
        $this->current = 0;

        return $this->proceed();
    }

    /**
     * Returns the object that holds the current joinpoint's static
     * part.
     *
     * @return object|null the object (can be null if the accessible object is
     * static).
     */
    public function getThis()
    {
        return null;
    }

    /**
     * Returns the static part of this joinpoint.
     *
     * @return ReflectionClass
     */
    public function getStaticPart()
    {
        return $this->reflectionClass;
    }

    /**
     * Returns a friendly description of current joinpoint
     *
     * @return string
     */
    final public function __toString()
    {
        return sprintf(
            "staticinitialization(%s)",
            $this->reflectionClass->getName()
        );
    }
}
