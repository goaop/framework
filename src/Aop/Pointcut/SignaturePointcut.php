<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\Support\SimpleSignatureFilter;

/**
 * Signature pointcut checks element signature (modifiers and name) to match it
 */
class SignaturePointcut extends SimpleSignatureFilter implements Pointcut
{
    use PointcutClassFilterTrait;
}
