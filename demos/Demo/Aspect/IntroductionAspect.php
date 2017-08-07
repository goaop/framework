<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Aspect;

use Go\Aop\Aspect;
use Go\Lang\Annotation\DeclareParents;

/**
 * Introduction aspect can dynamically add new interfaces and traits to the class
 */
class IntroductionAspect implements Aspect
{

    /**
     * Add a single interface and trait to the class.
     *
     * You can also give several interfaces/traits via []
     *
     * @DeclareParents(
     *   value="within(Demo\Example\IntroductionDemo)",
     *   interface="Serializable",
     *   defaultImpl="Demo\Aspect\Introduce\SerializableImpl"
     * )
     *
     * @var null
     */
    protected $introduction = null;
}
