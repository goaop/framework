<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Lang\Annotation;

/**
 * Pointcut annotation
 *
 * @Annotation
 * @Target("METHOD")
 *
 * @Attributes({
 *   @Attribute("value", type = "string", required=true)
 * })
 */
class Pointcut extends BaseAnnotation
{

}
