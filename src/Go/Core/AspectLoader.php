<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Doctrine\Common\Annotations\Reader;
use Go\Aop\Aspect;
use ReflectionClass;

/**
 * Loader of aspects into the container
 */
class AspectLoader
{

    /**
     * Aspect container instance
     *
     * @var null|AspectContainer
     */
    protected $container = null;

    /**
     * List of aspect loaders
     *
     * @var array
     */
    protected $loaders = array();

    /**
     * Annotation reader for aspects
     *
     * @var Reader|null
     */
    protected $annotationReader = null;

    /**
     * List of resources that was loaded
     *
     * @var array
     */
    protected $loadedResources = array();

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
     *
     * @param AspectLoaderExtension $loader Loader to register
     */
    public function registerLoaderExtension(AspectLoaderExtension $loader)
    {
        $targets = (array) $loader->getTarget();
        foreach ($targets as $target) {
            $this->loaders[$target][] = $loader;
        }
    }

    /**
     * Loads an aspect into the container with the help of aspect loaders
     *
     * @param \Go\Aop\Aspect $aspect
     */
    public function load(Aspect $aspect)
    {
        $refAspect = new \ReflectionClass($aspect);

        if (!empty($this->loaders[AspectLoaderExtension::TARGET_CLASS])) {
            $this->loadFrom($aspect, $refAspect, $this->loaders[AspectLoaderExtension::TARGET_CLASS]);
        }

        if (!empty($this->loaders[AspectLoaderExtension::TARGET_METHOD])) {
            $refMethods = $refAspect->getMethods();
            foreach ($refMethods as $refMethod) {
                $this->loadFrom($aspect, $refMethod, $this->loaders[AspectLoaderExtension::TARGET_METHOD]);
            }
        }

        if (!empty($this->loaders[AspectLoaderExtension::TARGET_PROPERTY])) {
            $refProperties = $refAspect->getProperties();
            foreach ($refProperties as $refProperty) {
                $this->loadFrom($aspect, $refProperty, $this->loaders[AspectLoaderExtension::TARGET_PROPERTY]);
            }
        }

        $this->loadedResources[] = $refAspect->getFileName();
    }

    /**
     * Load pointcuts into container
     *
     * There is no need to always load pointcuts, so we delay loading
     */
    public function loadAdvisorsAndPointcuts()
    {
        $containerResources = $this->container->getResources();
        $resourcesToLoad    = array_diff($containerResources, $this->loadedResources);

        if (!$resourcesToLoad) {
            return;
        }

        foreach ($this->container->getByTag('aspect') as $aspect) {
            $ref = new ReflectionClass($aspect);
            if (in_array($ref->getFileName(), $resourcesToLoad)) {
                $this->load($aspect);
            }
        }

        $this->loadedResources = $containerResources;
    }

    /**
     * Load definitions from specific aspect part into the aspect container
     *
     * @param Aspect $aspect Aspect instance
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $refPoint Reflection instance
     * @param array|AspectLoaderExtension[] $loaders List of loaders that can produce advisors from aspect class
     *
     * @throws \InvalidArgumentException If kind of loader isn't supported
     */
    protected function loadFrom(Aspect $aspect, $refPoint, array $loaders)
    {

        foreach ($loaders as $loader) {

            $loaderKind = $loader->getKind();
            switch ($loaderKind) {

                case AspectLoaderExtension::KIND_REFLECTION:
                    if ($loader->supports($aspect, $refPoint)) {
                        $loader->load($this->container, $aspect, $refPoint);
                    }
                    break;

                case AspectLoaderExtension::KIND_ANNOTATION:
                    $annotations = $this->getAnnotations($refPoint);
                    foreach ($annotations as $annotation) {
                        if ($loader->supports($aspect, $refPoint, $annotation)) {
                            $loader->load($this->container, $aspect, $refPoint, $annotation);
                        }
                    }
                    break;

                default:
                    throw new \InvalidArgumentException("Unsupported loader kind {$loaderKind}");

            }
        }
    }

    /**
     * Return list of annotations for reflection point
     *
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty $refPoint Reflection instance
     *
     * @return array list of annotations
     * @throws \InvalidArgumentException if $refPoint is unsupported
     */
    protected function getAnnotations($refPoint)
    {
        switch (true) {
            case ($refPoint instanceof \ReflectionClass):
                return $this->annotationReader->getClassAnnotations($refPoint);

            case ($refPoint instanceof \ReflectionMethod):
                return $this->annotationReader->getMethodAnnotations($refPoint);

            case ($refPoint instanceof \ReflectionProperty):
                return $this->annotationReader->getPropertyAnnotations($refPoint);

            default:
                throw new \InvalidArgumentException("Unsupported reflection point " . get_class($refPoint));
        }
    }
}
