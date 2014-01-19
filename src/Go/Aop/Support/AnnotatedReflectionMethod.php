<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2014, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use Doctrine\Common\Annotations\Reader;
use ReflectionMethod;

/**
 * Extended version of ReflectionMethod with annotation support
 */
class AnnotatedReflectionMethod extends ReflectionMethod
{
    /**
     * Annotation reader
     *
     * @var Reader
     */
    private static $annotationReader = null;

    /**
     * Gets a method annotation.
     *
     * @param string $annotationName The name of the annotation.
     * @return mixed The Annotation or NULL, if the requested annotation does not exist.
     */
    public function getAnnotation($annotationName)
    {
       return self::$annotationReader->getMethodAnnotation($this, $annotationName);
    }

    /**
     * Gets the annotations applied to a method.
     *
     * @return array An array of Annotations.
     */
    public function getAnnotations()
    {
        return self::$annotationReader->getMethodAnnotations($this);
    }

    /**
     * Injects an annotation reader
     *
     * @param Reader $reader
     */
    public static function injectAnnotationReader(Reader $reader)
    {
        self::$annotationReader = $reader;
    }
}