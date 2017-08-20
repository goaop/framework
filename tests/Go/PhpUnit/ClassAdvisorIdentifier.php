<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\PhpUnit;

/**
 * Value object class for class member weaving constraints.
 */
final class ClassAdvisorIdentifier
{
    /**
     * @var string
     */
    private $class;

    /**
     * @var string
     */
    private $subject;

    /**
     * @var string
     */
    private $advisorIdentifier;

    /**
     * @var string
     */
    private $target;

    /**
     * @var null|int
     */
    private $index;

    public function __construct($class, $subject, $advisorIdentifier, $target, $index = null)
    {
        $this->class             = is_object($class) ? get_class($class) : $class;
        $this->subject           = $subject;
        $this->advisorIdentifier = $advisorIdentifier;
        $this->target            = $target;
        $this->index             = $index;
    }

    /**
     * Get full qualified class name.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Get name of subject (method, property...) of interception.
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Get get advisor identifier.
     *
     * @return string
     */
    public function getAdvisorIdentifier()
    {
        return $this->advisorIdentifier;
    }

    /**
     * Get weaving target.
     *
     * @see \Go\Core\AspectContainer::METHOD_PREFIX
     * @see \Go\Core\AspectContainer::PROPERTY_PREFIX
     * @see \Go\Core\AspectContainer::STATIC_METHOD_PREFIX
     *
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Get join point ordering index.
     *
     * @return int|null
     */
    public function getIndex()
    {
        return $this->index;
    }
}
