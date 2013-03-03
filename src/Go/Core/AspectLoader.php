<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

use Go\Aop\Aspect;

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
     * Loader constructor
     */
    public function __construct(AspectContainer $container)
    {
        $this->container = $container;
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
    final public function load(Aspect $aspect)
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
        /** @var $annotationReader \Doctrine\Common\Annotations\Reader */
        $annotationReader = $this->container->get('aspect.annotation.reader');
        switch (true) {
            case ($refPoint instanceof \ReflectionClass):
                return $annotationReader->getClassAnnotations($refPoint);

            case ($refPoint instanceof \ReflectionMethod):
                return $annotationReader->getMethodAnnotations($refPoint);

            case ($refPoint instanceof \ReflectionProperty):
                return $annotationReader->getPropertyAnnotations($refPoint);

            default:
                throw new \InvalidArgumentException("Unsupported reflection point " . get_class($refPoint));
        }
    }
}
