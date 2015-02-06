<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Framework\Block\ClosureDynamicProceedTrait;
use Go\Aop\Framework\Block\SimpleInvocationTrait;

class DynamicClosureMethodInvocation extends AbstractMethodInvocation
{
    use ClosureDynamicProceedTrait;
    use SimpleInvocationTrait;
}
