<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Lang\Annotation;

/**
 * Default interceptor class with common attributes
 */
class BaseInterceptor extends BaseAnnotation implements Interceptor
{
    /**
     * Order for advice
     *
     * @var integer
     */
    public $order = 0;

    /**
     * Name of the pointcut to use
     *
     * @var string
     */
    public $pointcut = null;
}
