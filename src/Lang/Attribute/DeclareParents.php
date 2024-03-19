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
 * Declare parents annotation
 *
 * @Annotation
 * @Target("PROPERTY")
 *
 * @Attributes({
 *   @Attribute("value", type = "string", required=true),
 *   @Attribute("interface", type = "string"),
 *   @Attribute("defaultImpl", type = "string")
 * })
 */
class DeclareParents extends BaseAnnotation
{
    /**
     * Default implementation (trait name)
     */
    public string $defaultImpl;

    /**
     * Interface name to add
     */
    public string $interface;
}
