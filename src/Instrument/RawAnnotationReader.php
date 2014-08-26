<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use TokenReflection\ReflectionClass as ParsedReflectionClass;
use TokenReflection\ReflectionMethod as ParsedReflectionMethod;
use TokenReflection\ReflectionProperty as ParsedReflectionProperty;
use Doctrine\Common\Annotations\DocParser;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Simple Raw Annotation Reader.
 *
 * This annotation reader is intended to be used in projects where you have
 * full-control over all annotations that are available.
 *
 * @since  2.2
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class RawAnnotationReader
{
    /**
     * @var DocParser
     */
    private $parser;

    /**
     * Constructor.
     *
     * Initializes a new SimpleAnnotationReader.
     */
    public function __construct()
    {
        $this->parser = new DocParser();
        $this->parser->setIgnoreNotImportedAnnotations(true);
    }

    /**
     * Set imports for annotations
     *
     * @param array $imports
     */
    public function setImports(array $imports)
    {
        $convertedImports = array();
        foreach ($imports as $aliasName=>$fullName) {
            $convertedImports[strtolower($aliasName)] = $fullName;
        }
        $this->parser->setImports($convertedImports);
    }

    /**
     * Adds a namespace in which we will look for annotations.
     *
     * @param string $namespace
     */
    public function addNamespace($namespace)
    {
        $this->parser->addNamespace($namespace);
    }

    /**
     * Gets the annotations applied to a class.
     *
     * @param ReflectionClass|ParsedReflectionClass $class The ReflectionClass of the class from which
     *                               the class annotations should be read.
     * @return array An array of Annotations.
     */
    public function getClassAnnotations($class)
    {
        $this->parser->setTarget(Target::TARGET_CLASS);

        return $this->parser->parse($class->getDocComment(), 'class '.$class->getName());
    }

     /**
     * Gets the annotations applied to a method.
     *
     * @param ReflectionMethod|ParsedReflectionMethod $method The ReflectionMethod of the method from which
     *                                   the annotations should be read.
     * @return array An array of Annotations.
     */
    public function getMethodAnnotations($method)
    {
        $this->parser->setTarget(Target::TARGET_METHOD);

        return $this->parser->parse($method->getDocComment(), 'method '.$method->getDeclaringClass()->name.'::'.$method->getName().'()');
    }

    /**
     * Gets the annotations applied to a property.
     *
     * @param ReflectionProperty|ParsedReflectionProperty $property The ReflectionProperty of the property
     *                                     from which the annotations should be read.
     * @return array An array of Annotations.
     */
    public function getPropertyAnnotations($property)
    {
        $this->parser->setTarget(Target::TARGET_PROPERTY);

        return $this->parser->parse($property->getDocComment(), 'property '.$property->getDeclaringClass()->name.'::$'.$property->getName());
    }

    /**
     * Gets a class annotation.
     *
     * @param ReflectionClass|ParsedReflectionClass $class The ReflectionClass of the class from which
     *                               the class annotations should be read.
     * @param string $annotationName The name of the annotation.
     * @return The Annotation or NULL, if the requested annotation does not exist.
     */
    public function getClassAnnotation($class, $annotationName)
    {
        foreach ($this->getClassAnnotations($class) as $annot) {
            if ($annot instanceof $annotationName) {
                return $annot;
            }
        }

        return null;
    }

    /**
     * Gets a method annotation.
     *
     * @param ReflectionMethod|ParsedReflectionMethod $method
     * @param string $annotationName The name of the annotation.
     * @return The Annotation or NULL, if the requested annotation does not exist.
     */
    public function getMethodAnnotation($method, $annotationName)
    {
        foreach ($this->getMethodAnnotations($method) as $annot) {
            if ($annot instanceof $annotationName) {
                return $annot;
            }
        }

        return null;
    }

    /**
     * Gets a property annotation.
     *
     * @param ReflectionProperty|ParsedReflectionProperty $property
     * @param string $annotationName The name of the annotation.
     * @return The Annotation or NULL, if the requested annotation does not exist.
     */
    public function getPropertyAnnotation($property, $annotationName)
    {
        foreach ($this->getPropertyAnnotations($property) as $annot) {
            if ($annot instanceof $annotationName) {
                return $annot;
            }
        }

        return null;
    }
}
