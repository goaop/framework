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

namespace Demo\Showcase;

use Closure;
use Go\Aop\Aspect;
use ReflectionClass;
use ReflectionObject;

/**
 * One example of the demo: an aspect and the scenario that shows it in action
 */
final readonly class Showcase
{
    /**
     * @param string                    $id       Identifier used in the URL
     * @param string                    $group    Navigation group of the showcase
     * @param string                    $title    Short title for the navigation
     * @param string                    $summary  One sentence about what the aspect does
     * @param class-string<Aspect>      $aspect   Aspect demonstrated by the showcase
     * @param Closure(): (object|null)  $scenario Example code, returns the object whose class is shown
     */
    public function __construct(
        public string $id,
        public string $group,
        public string $title,
        public string $summary,
        public string $aspect,
        private Closure $scenario,
    ) {}

    /**
     * Short name of the aspect class
     */
    public function aspectName(): string
    {
        return new ReflectionClass($this->aspect)->getShortName();
    }

    /**
     * Runs the scenario and collects its output together with the source code involved
     */
    public function run(): ShowcaseResult
    {
        $startedAt = hrtime(true);
        ob_start();
        try {
            $subject = ($this->scenario)();
        } finally {
            $output = (string) ob_get_clean();
        }
        $elapsedMs = (hrtime(true) - $startedAt) / 1e6;

        return new ShowcaseResult(
            output: $output,
            elapsedMs: $elapsedMs,
            usage: SourceCode::ofClosure($this->scenario),
            aspect: SourceCode::ofClass(new ReflectionClass($this->aspect)),
            subject: $subject !== null ? SourceCode::ofClass(new ReflectionObject($subject)) : null,
        );
    }
}
