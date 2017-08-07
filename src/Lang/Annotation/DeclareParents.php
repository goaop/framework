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
 * Declare parents annotation
 *
 * @Annotation
 * @Target("PROPERTY")
 *
 * @Attributes({
 *   @Attribute("value", type = "string", required=true),
 *   @Attribute("interface", type = "array"),
 *   @Attribute("defaultImpl", type = "array")
 * })
 */
class DeclareParents extends BaseAnnotation
{
    /**
     * Default implementation (trait name)
     *
     * @var string
     */
    public $defaultImpl = null;

    /**
     * Interface name to add
     *
     * @var string
     */
    public $interface = null;
}
