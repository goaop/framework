<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Lang\Annotation;

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
     *
     * @var string
     */
    public $level = E_USER_NOTICE;
}
