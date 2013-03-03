<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use ReflectionMethod;

use Go\Instrument\RawAnnotationReader;

use TokenReflection\ReflectionMethod as ParsedReflectionMethod;

/**
 * Annotation method pointcut checks method annotation
 */
class AnnotationMethodPointcut extends StaticMethodMatcherPointcut
{
    /**
     * Annotation class to match
     *
     * @var string
     */
    protected $annotationName = '';

    /**
     * Annotation reader
     *
     * @var null|RawAnnotationReader
     */
    protected $annotationReader = null;

    /**
     * Annotation method matcher constructor
     *
     * @param RawAnnotationReader $reader Annotation reader
     * @param string $annotationName Name of the annotation class to match
     */
    public function __construct(RawAnnotationReader $reader, $annotationName)
    {
        $this->annotationName  = $annotationName;
        $this->annotationReader = $reader;
    }

    /**
     * Performs matching of point of code
     *
     * @param $method ReflectionMethod|ParsedReflectionMethod Specific part of code, can be any Reflection class
     *
     * @return bool
     */
    public function matches($method)
    {
        /** @var $method ReflectionMethod|ParsedReflectionMethod */
        if (!$method instanceof ReflectionMethod && !$method instanceof ParsedReflectionMethod) {
            return false;
        }
        if ($method instanceof ParsedReflectionMethod) {
            $imports = $method->getNamespaceAliases();
            $this->annotationReader->setImports($imports);
        }
        $annotation = $this->annotationReader->getMethodAnnotation($method, $this->annotationName);
        return (bool) $annotation;
    }
}
