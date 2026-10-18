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

/**
 * Everything the page shows about an executed showcase
 */
final readonly class ShowcaseResult
{
    /**
     * @param string          $output    Text printed by the scenario and the advices
     * @param float           $elapsedMs Execution time of the scenario
     * @param SourceCode      $usage     Code of the scenario itself
     * @param SourceCode      $aspect    Source code of the aspect
     * @param SourceCode|null $subject   Source code of the class the scenario works with
     */
    public function __construct(
        public string $output,
        public float $elapsedMs,
        public SourceCode $usage,
        public SourceCode $aspect,
        public ?SourceCode $subject,
    ) {}

    /**
     * @return array<string, SourceCode> Source code panels indexed by their label
     */
    public function sources(): array
    {
        return array_filter(
            ['Usage' => $this->usage, 'Aspect' => $this->aspect, 'Class' => $this->subject],
            static fn(?SourceCode $source): bool => $source !== null,
        );
    }
}
