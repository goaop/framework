<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\PointFilter;
use Go\Instrument\RawAnnotationReader;
use ReflectionClass;
use TokenReflection\ReflectionClass as ParsedReflectionClass;

/**
 * Annotation class filter that matches class by annotation class name
 */
class AnnotationClassFilter implements PointFilter
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
     * Annotation matcher constructor
     *
     * @param RawAnnotationReader $reader Reader of annotations
     * @param string $annotationName Annotation class name to match
     */
    public function __construct(RawAnnotationReader $reader, $annotationName)
    {
        $this->annotationName   = $annotationName;
        $this->annotationReader = $reader;
    }

    /**
     * {@inheritdoc}
     */
    public function matches($class)
    {
        /** @var $point ReflectionClass|ParsedReflectionClass */
        if (!$class instanceof ReflectionClass && !$class instanceof ParsedReflectionClass) {
            return false;
        }
        if ($class instanceof ParsedReflectionClass) {
            $imports = $class->getNamespaceAliases();
            $this->annotationReader->setImports($imports);
        }
        $annotation = $this->annotationReader->getClassAnnotation($class, $this->annotationName);

        return (bool) $annotation;
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return self::KIND_CLASS;
    }
}
