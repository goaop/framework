<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

use ReflectionProperty;

/**
 * This interface represents a field access in the program.
 *
 * <p>A field access is a joinpoint and can be intercepted by a field
 * interceptor.
 *
 * @see FieldInterceptor
 */
interface FieldAccess extends Joinpoint
{

    /**
     * The read access type (see {@link getAccessType()}).
     */
    const READ = 0;

    /**
     * The write access type (see {@link getAccessType()}).
     */
    const WRITE = 1;

    /**
     * Gets the field being accessed.
     *
     * <p>This method is a friendly implementation of the
     * {@link Joinpoint::getStaticPart()} method (same result).
     *
     * @return ReflectionProperty the field being accessed.
     */
    public function getField();

    /**
     * Gets the value that must be set to the field.
     *
     * <p>This value can be intercepted and changed by a field
     * interceptor.
     *
     * @return mixed
     */
    public function getValueToSet();

    /**
     * Returns the access type.
     *
     * @return integer
     */
    public function getAccessType();
}
