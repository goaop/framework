<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Lang\Attribute;

/**
 * Declare error annotation
 *
 * @Annotation
 * @Target("PROPERTY")
 *
 * @Attributes({
 *   @Attribute("value", type = "string", required=true),
 *   @Attribute("level", type = "integer"),
 * })
 */
class DeclareError extends BaseAnnotation
{
    /**
     * Interface name to add
     */
    public int $level = E_USER_NOTICE;
}
