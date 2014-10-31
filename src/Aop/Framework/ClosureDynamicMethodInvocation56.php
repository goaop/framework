<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Framework\Block\ClosureSplatDynamicProceedTrait;
use Go\Aop\Framework\Block\SimpleInvocationTrait;

/**
 * Class-invocation of dynamic method in a class via closure rebinding for version PHP>=5.6
 *
 * This class uses splat operator '...' for faster invocation
 */
class ClosureDynamicMethodInvocation56 extends AbstractMethodInvocation
{
    use SimpleInvocationTrait;
    use ClosureSplatDynamicProceedTrait;
}
