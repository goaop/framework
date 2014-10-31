<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Framework\Block\ReflectionProceedTrait;
use Go\Aop\Framework\Block\SimpleInvocationTrait;

/**
 * Reflective method invocation implementation
 */
class ReflectionMethodInvocation extends AbstractMethodInvocation
{
    use SimpleInvocationTrait;
    use ReflectionProceedTrait;
}
