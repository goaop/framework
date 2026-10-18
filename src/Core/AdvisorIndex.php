<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Advisor;
use Go\Aop\Pointcut;
use Go\Aop\PointcutAdvisor;

/**
 * Index of the advisors of every registered aspect and of the pointcut kinds they use
 *
 * Lets the transformation pipeline enable the machinery a pointcut kind needs (e.g. the rewriting of `new` for
 * initialization pointcuts) only while an advisor of that kind is registered. Aspects that are not loaded yet are
 * loaded on the first query, and the kinds are collected again after every registration in the container, so an
 * aspect registered or loaded later is taken into account as well.
 *
 * @internal Framework service, not a public extension point
 */
final class AdvisorIndex
{
    /**
     * Advisors the kinds below were collected from
     *
     * @var array<Advisor>|null
     */
    private ?array $indexedAdvisors = null;

    /**
     * Bitmask of the pointcut kinds of the indexed advisors
     */
    private int $pointcutKinds = 0;

    public function __construct(
        private readonly AspectContainer $container,
        private readonly AspectLoaderInterface $aspectLoader,
    ) {}

    /**
     * Returns the advisors of every registered aspect, loading the aspects that are not loaded yet
     *
     * @return array<Advisor>
     */
    public function getAdvisors(): array
    {
        foreach ($this->aspectLoader->getUnloadedAspects() as $unloadedAspect) {
            $this->aspectLoader->loadAndRegister($unloadedAspect);
        }

        return $this->container->getServicesByInterface(Advisor::class);
    }

    /**
     * Checks whether a pointcut of any registered advisor can match a join point of the given kind
     *
     * @param int $pointcutKind One of the Pointcut::KIND_* constants
     */
    public function hasPointcutKind(int $pointcutKind): bool
    {
        $advisors = $this->getAdvisors();
        // The container returns the same array until the next registration, compared without a walk
        if ($advisors !== $this->indexedAdvisors) {
            $pointcutKinds = 0;
            foreach ($advisors as $advisor) {
                if ($advisor instanceof PointcutAdvisor) {
                    $pointcutKinds |= $advisor->pointcut->kind;
                }
            }
            $this->indexedAdvisors = $advisors;
            $this->pointcutKinds   = $pointcutKinds;
        }

        return ($this->pointcutKinds & $pointcutKind) !== 0;
    }
}
