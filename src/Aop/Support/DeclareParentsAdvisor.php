<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\IntroductionAdvisor;
use Go\Aop\IntroductionInfo;
use Go\Aop\PointFilter;

/**
 * Introduction advisor delegating to the given object.
 */
class DeclareParentsAdvisor implements IntroductionAdvisor
{

    /**
     * Information about introduced interface/trait
     *
     * @var IntroductionInfo
     */
    private $advice;

    /**
     * Type pattern the introduction is restricted to
     *
     * @var PointFilter
     */
    private $classFilter;

    /**
     * Create a DefaultIntroductionAdvisor for the given advice.
     */
    public function __construct(PointFilter $classFilter, IntroductionInfo $info)
    {
        $this->classFilter = $classFilter;
        $this->advice      = $info;
    }

    /**
     * Returns an advice to apply
     *
     * @return IntroductionInfo
     */
    public function getAdvice()
    {
        return $this->advice;
    }

    /**
     * Return the filter determining which target classes this introduction should apply to.
     *
     * This represents the class part of a pointcut. Note that method matching doesn't make sense to introductions.
     *
     * @return PointFilter The class filter
     */
    public function getClassFilter()
    {
        return $this->classFilter;
    }
}
