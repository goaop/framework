<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

/**
 * Class with promoted constructor properties (multi-line constructor) used for
 * testing interception of promoted properties (issue #599).
 */
class PromotedPropertyClass
{
    public function __construct(
        private string $name = 'initial',
        public private(set) int $counter = 1,
        protected ?\ArrayObject $bag = null,
    ) {
        $this->name = trim($this->name);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
