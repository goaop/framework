<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use Reflector;

/**
 * Extension interface that defines an API for aspect loaders
 */
interface AspectLoaderExtension
{

    /**
     * Loader works with class-level definition of aspect
     */
    const TARGET_CLASS = 'class';

    /**
     * Loader works with method definitions of aspect
     */
    const TARGET_METHOD = 'method';

    /**
     * Loader works with property definitions of aspect
     */
    const TARGET_PROPERTY = 'property';

    /**
     * Loader works only with reflections of aspect class, method or property
     */
    const KIND_REFLECTION = 'reflection';

    /**
     * Loader works with each annotation defined for aspect class, method or property.
     */
    const KIND_ANNOTATION = 'annotation';

    /**
     * Return kind of loader, can be one of KIND_REFLECTION or KIND_ANNOTATION
     *
     * For loader that works with annotations additional metaInformation will be passed
     */
    public function getKind() : string;

    /**
     * Returns one or more target for loader, see TARGET_XXX constants
     */
    public function getTargets() : array;

    /**
     * Checks if loader is able to handle specific point of aspect
     *
     * @param Aspect $aspect Instance of aspect
     * @param mixed|\ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     * @param mixed|null $metaInformation Additional meta-information, e.g. annotation for method
     *
     * @return boolean true if extension is able to create an advisor from reflection and metaInformation
     */
    public function supports(Aspect $aspect, $reflection, $metaInformation = null) : bool;

    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param Aspect $aspect Instance of aspect
     * @param Reflector $reflection Reflection of point
     * @param mixed|null $metaInformation Additional meta-information, e.g. annotation for method
     *
     * @return array|Pointcut[]|Advisor[]
     */
    public function load(Aspect $aspect, Reflector $reflection, $metaInformation = null) : array;
}
