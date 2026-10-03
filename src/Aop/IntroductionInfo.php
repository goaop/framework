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
 * Interface supplying the information necessary to describe an introduction of trait.
 *
 * An advice implementing this is self-describing: besides the behavior, it names the trait and
 * the interface it introduces into the matched classes.
 */
interface IntroductionInfo extends Advice
{
    /**
     * Returns the additional interface introduced by this Advisor or Advice.
     *
     * @return class-string
     */
    public function getInterface(): string;

    /**
     * Return the additional trait with realization of introduced interface
     *
     * @return trait-string
     */
    public function getTrait(): string;
}
