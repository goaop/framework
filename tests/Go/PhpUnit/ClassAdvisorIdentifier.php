<?php

declare(strict_types=1);
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
    private string $class;

    private string $subject;

    private string $target;

    private ?string $advisorIdentifier;

    private ?int $index;

    public function __construct(
        $class,
        string $subject,
        string $target,
        string $advisorIdentifier = null,
        int $index = null
    ) {
        $this->class             = is_object($class) ? get_class($class) : $class;
        $this->subject           = $subject;
        $this->advisorIdentifier = $advisorIdentifier;
        $this->target            = $target;
        $this->index             = $index;
    }

    /**
     * Get full qualified class name.
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Get name of subject (method, property...) of interception.
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * Get weaving target.
     *
     * @see AspectContainer::METHOD_PREFIX
     * @see AspectContainer::PROPERTY_PREFIX
     * @see AspectContainer::STATIC_METHOD_PREFIX
     * @see AspectContainer::INIT_PREFIX
     * @see AspectContainer::STATIC_INIT_PREFIX
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * Get get advisor identifier.
     */
    public function getAdvisorIdentifier(): ?string
    {
        return $this->advisorIdentifier;
    }

    /**
     * Get join point ordering index.
     */
    public function getIndex(): ?int
    {
        return $this->index;
    }
}
