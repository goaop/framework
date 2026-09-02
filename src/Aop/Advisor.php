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

namespace Go\Aop;

/**
 * Base interface holding AOP advice (action to take at a joinpoint)
 *
 * Every advisor can compile itself into a plain-PHP expression for the advisor cache.
 */
interface Advisor extends Compilable
{
    /**
     * Return the advice part of this aspect. An advice may be an interceptor, a before advice, a throws advice, etc.
     *
     * @api
     */
    public function getAdvice(): Advice;
}
