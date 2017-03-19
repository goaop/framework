<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Aop\Intercept;

/**
 * This interface represents a generic runtime joinpoint (in the AOP terminology).
 *
 * A runtime joinpoint is an event that occurs on a static joinpoint (i.e. a location in a the program).
 * For instance, an invocation is the runtime joinpoint on a method (static joinpoint).
 * The static part of a given joinpoint can be generically retrieved using the getStaticPart() method.
 *
 * In the context of an interception framework, a runtime joinpoint is then the reification of an access to
 * an accessible object (a method, a constructor, a field), i.e. the static part of the joinpoint. It is passed
 * to the interceptors that are installed on the static joinpoint.
 *
 * @see Interceptor
 * @api
 */
interface Joinpoint
{

    /**
     * Proceeds to the next interceptor in the chain.
     *
     * @api
     *
     * @return mixed see the children interfaces' proceed definition.
     */
    public function proceed();

    /**
     * Returns the object that holds the current joinpoint's static
     * part.
     *
     * @api
     *
     * @return object|string the object for dynamic call or string with name of scope
     */
    public function getThis();

    /**
     * Returns the static part of this joinpoint.
     *
     * @return object
     */
    public function getStaticPart();

    /**
     * Returns a friendly description of current joinpoint
     *
     * @return string
     */
    public function __toString();
}
