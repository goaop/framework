<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop;

/**
 * Interceptor base class
 *
 *   A generic interceptor can intercept runtime events that occur within a base program. Those events are materialized
 * by joinpoints. Runtime joinpoints can be invocations, field access, exceptions. This interface is not used directly.
 * Use the the sub-interfaces to intercept specific events
 *
 * @package go
 * @subpackage aop
 * @see Joinpoint
 */
interface Interceptor extends Advice
{

}
