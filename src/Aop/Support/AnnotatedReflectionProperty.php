<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Doctrine\Common\Annotations\Reader;
use Go\Core\AspectKernel;
use ReflectionProperty;

/**
 * Extended version of ReflectionProperty with annotation support
 */
class AnnotatedReflectionProperty extends ReflectionProperty implements AnnotationAccess
{
    /**
     * Annotation reader
     *
     * @var Reader
     */
    private static $annotationReader;

    /**
     * Gets a property annotation.
     *
     * @param string $annotationName The name of the annotation.
     * @return mixed The Annotation or NULL, if the requested annotation does not exist.
     */
    public function getAnnotation(string $annotationName)
    {
        return self::getReader()->getPropertyAnnotation($this, $annotationName);
    }

    /**
     * Gets the annotations applied to a property.
     */
    public function getAnnotations(): array
    {
        return self::getReader()->getPropertyAnnotations($this);
    }

    /**
     * Returns an annotation reader
     */
    private static function getReader(): Reader
    {
        if (!self::$annotationReader) {
            self::$annotationReader = AspectKernel::getInstance()->getContainer()->get('aspect.annotation.reader');
        }

        return self::$annotationReader;
    }
}
