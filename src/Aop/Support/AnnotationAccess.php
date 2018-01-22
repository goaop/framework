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

/**
 * Provides access to annotations from reflection class/property/method.
 */
interface AnnotationAccess
{
    /**
     * Gets a annotation.
     *
     * @param string $annotationName The name of the annotation.
     * @return mixed The Annotation or NULL, if the requested annotation does not exist.
     */
    public function getAnnotation(string $annotationName);

    /**
     * Gets the annotations.
     */
    public function getAnnotations(): array;
}
