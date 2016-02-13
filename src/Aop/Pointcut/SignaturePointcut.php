<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\PointFilter;

/**
 * Signature pointcut checks element signature (modifiers and name) to match it
 */
class SignaturePointcut implements Pointcut
{
    use PointcutClassFilterTrait;

    /**
     * Element name to match, can contain wildcards **,*,?,|
     *
     * @var string
     */
    protected $name = '';

    /**
     * Modifier filter for element
     *
     * @var PointFilter
     */
    protected $modifierFilter;

    /**
     * Filter kind
     *
     * @var int
     */
    protected $filterKind = 0;

    /**
     * Signature matcher constructor
     *
     * @param integer $filterKind Kind of filter, e.g. KIND_CLASS
     * @param string $name Name of the entity to match or glob pattern
     * @param PointFilter $modifierFilter Method modifier filter
     */
    public function __construct($filterKind, $name, PointFilter $modifierFilter)
    {
        $this->filterKind = $filterKind;
        $this->name       = $name;
        $this->regexp     = strtr(preg_quote($this->name, '/'), array(
            '\\*'    => '[^\\\\]+?',
            '\\*\\*' => '.+?',
            '\\?'    => '.',
            '\\|'    => '|'
        ));
        $this->modifierFilter = $modifierFilter;
    }

    /**
     * {@inheritdoc}
     */
    public function matches($point)
    {
        if (!$this->modifierFilter->matches($point)) {
            return false;
        }

        return ($point->name === $this->name) || (bool) preg_match("/^(?:{$this->regexp})$/", $point->name);
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return $this->filterKind;
    }
}
