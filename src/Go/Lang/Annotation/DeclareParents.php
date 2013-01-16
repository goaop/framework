<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Lang\Annotation;

/**
 * Declare parents annotation
 *
 * @Annotation
 * @Target("PROPERTY")
 */
class DeclareParents extends BaseAnnotation
{
    /**
     * Default implementation (trait name)
     *
     * @var string
     */
    public $defaultImpl = '';

    /**
     * Interface name to add
     *
     * @var string
     */
    public $interface = '';
}
