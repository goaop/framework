<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Framework\Block\ReflectionProceedTrait;
use Go\Aop\Framework\Block\SimpleInvocationTrait;

/**
 * @deprecated since 1.0.0
 */
class DynamicReflectionMethodInvocation extends AbstractMethodInvocation
{
    use ReflectionProceedTrait;
    use SimpleInvocationTrait;
}
