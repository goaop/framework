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

use Doctrine\Common\Annotations\Reader;
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Reflector;
use function get_class;

/**
 * Loader of aspects into the container
 */
class AspectLoader
{
    /**
     * Aspect container instance
     */
    protected $container;

    /**
     * List of aspect loaders
     *
     * @var AspectLoaderExtension[][]
     */
    protected $loaders = [];

    /**
     * Annotation reader for aspects
     */
    protected $annotationReader;

    /**
     * List of aspect class names that were loaded
     *
     * @var string[]
     */
    protected $loadedAspects = [];

    /**
     * Loader constructor
     */
    public function __construct(AspectContainer $container, Reader $reader)
    {
        $this->container        = $container;
        $this->annotationReader = $reader;
    }

    /**
     * Register an aspect loader extension
     *
     * This method allows to extend the logic of aspect loading by registering an extension for loader.
     */
    public function registerLoaderExtension(AspectLoaderExtension $loader): void
    {
        $targets = $loader->getTargets();
        foreach ($targets as $target) {
            $this->loaders[$target][] = $loader;
        }
    }

    /**
     * Loads an aspect with the help of aspect loaders, but don't register it in the container
     *
     * @see loadAndRegister() method for registration
     *
     * @return Pointcut[]|Advisor[]
     */
    public function load(Aspect $aspect): array
    {
        $loadedItems = [];
        $refAspect   = new \ReflectionClass($aspect);

        if (!empty($this->loaders[AspectLoaderExtension::TARGET_CLASS])) {
            $loadedItems += $this->loadFrom($aspect, $refAspect, $this->loaders[AspectLoaderExtension::TARGET_CLASS]);
        }

        if (!empty($this->loaders[AspectLoaderExtension::TARGET_METHOD])) {
            $refMethods = $refAspect->getMethods();
            foreach ($refMethods as $refMethod) {
                $loadedItems += $this->loadFrom($aspect, $refMethod, $this->loaders[AspectLoaderExtension::TARGET_METHOD]);
            }
        }

        if (!empty($this->loaders[AspectLoaderExtension::TARGET_PROPERTY])) {
            $refProperties = $refAspect->getProperties();
            foreach ($refProperties as $refProperty) {
                $loadedItems += $this->loadFrom($aspect, $refProperty, $this->loaders[AspectLoaderExtension::TARGET_PROPERTY]);
            }
        }

        return $loadedItems;
    }

    /**
     * Loads and register all items of aspect in the container
     */
    public function loadAndRegister(Aspect $aspect): void
    {
        $loadedItems = $this->load($aspect);
        foreach ($loadedItems as $itemId => $item) {
            if ($item instanceof Pointcut) {
                $this->container->registerPointcut($item, $itemId);
            }
            if ($item instanceof Advisor) {
                $this->container->registerAdvisor($item, $itemId);
            }
        }

        $aspectClass = get_class($aspect);
        $this->loadedAspects[$aspectClass] = $aspectClass;
    }

    /**
     * Returns list of unloaded aspects in the container
     *
     * @return Aspect[]
     */
    public function getUnloadedAspects(): array
    {
        $unloadedAspects = [];

        foreach ($this->container->getByTag('aspect') as $aspect) {
            if (!isset($this->loadedAspects[get_class($aspect)])) {
                $unloadedAspects[] = $aspect;
            }
        }

        return $unloadedAspects;
    }

    /**
     * Load definitions from specific aspect part into the aspect container
     *
     * @param Aspect $aspect Aspect instance
     * @param Reflector $reflector Reflection instance
     * @param array|AspectLoaderExtension[] $loaders List of loaders that can produce advisors from aspect class
     *
     * @throws \InvalidArgumentException If kind of loader isn't supported
     *
     * @return array|Pointcut[]|Advisor[]
     */
    protected function loadFrom(Aspect $aspect, Reflector $reflector, array $loaders): array
    {
        $loadedItems = [];

        foreach ($loaders as $loader) {
            $loaderKind = $loader->getKind();
            switch ($loaderKind) {
                case AspectLoaderExtension::KIND_REFLECTION:
                    if ($loader->supports($aspect, $reflector)) {
                        $loadedItems += $loader->load($aspect, $reflector);
                    }
                    break;

                case AspectLoaderExtension::KIND_ANNOTATION:
                    $annotations = $this->getAnnotations($reflector);
                    foreach ($annotations as $annotation) {
                        if ($loader->supports($aspect, $reflector, $annotation)) {
                            $loadedItems += $loader->load($aspect, $reflector, $annotation);
                        }
                    }
                    break;

                default:
                    throw new InvalidArgumentException("Unsupported loader kind {$loaderKind}");
            }
        }

        return $loadedItems;
    }

    /**
     * Return list of annotations for reflection point
     *
     * @return array list of annotations
     * @throws \InvalidArgumentException if $reflector is unsupported
     */
    protected function getAnnotations(Reflector $reflector): array
    {
        switch (true) {
            case ($reflector instanceof ReflectionClass):
                return $this->annotationReader->getClassAnnotations($reflector);

            case ($reflector instanceof ReflectionMethod):
                return $this->annotationReader->getMethodAnnotations($reflector);

            case ($reflector instanceof ReflectionProperty):
                return $this->annotationReader->getPropertyAnnotations($reflector);

            default:
                throw new InvalidArgumentException('Unsupported reflection point ' . get_class($reflector));
        }
    }
}
