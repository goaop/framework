<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Tests\TestProject\Application\InconsistentlyWeavedClass;
use Go\Lang\Attribute as Pointcut;

/**
 * Apspect can not depend on class that is subject of its weaving
 * if that class is being weaved after aspect is registered.
 *
 * @see https://github.com/goaop/framework/issues/338
 * @see https://github.com/goaop/goaop-symfony-bundle/issues/15
 */
class InconsistentlyWeavingAspect implements Aspect
{
    public function __construct(InconsistentlyWeavedClass $problematicClass)
    {
        /* noop */
    }

    /**
     * Intercepts \Go\Tests\TestProject\Application\InconsistentlyWeavedClass\badlyWeaved() on which
     * this aspects depends in constructor, therefor, it is already loaded and can not be weaved.
     */
    #[Pointcut\After("execution(public Go\Tests\TestProject\Application\InconsistentlyWeavedClass->badlyWeaved(*))")]
    public function weaveBadly()
    {
        echo 'I weave badly.';
    }
}
