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
     * Gets concrete annotation by name or null if the requested annotation does not exist.
     */
    public function getAnnotation(string $annotationName): ?object;

    /**
     * Gets all annotations applied to the current item.
     */
    public function getAnnotations(): array;
}
