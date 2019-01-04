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
use function strlen;

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
     * Constructor for the class static initialization joinpoint
     *
     * @param $advices array List of advices for this invocation
     */
    public function __construct(string $className, string $unusedType, array $advices)
    {
        $originalClass = $className;
        if (strpos($originalClass, AspectContainer::AOP_PROXIED_SUFFIX)) {
            $originalClass = substr($originalClass, 0, -strlen(AspectContainer::AOP_PROXIED_SUFFIX));
        }
        $this->reflectionClass = new \ReflectionClass($originalClass);
        parent::__construct($advices);
    }

    /**
     * Proceeds to the next interceptor in the chain.
     *
     * @return void Static initializtion could not return anything
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
     */
    final public function __invoke(): void
    {
        $this->current = 0;
        $this->proceed();
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
     */
    final public function __toString(): string
    {
        return sprintf(
            'staticinitialization(%s)',
            $this->reflectionClass->getName()
        );
    }
}
