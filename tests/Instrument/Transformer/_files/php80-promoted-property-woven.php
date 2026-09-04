<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

/**
 * Class with promoted constructor properties (multi-line constructor) used for
 * testing interception of promoted properties (issue #599).
 */
trait PromotedPropertyClassOriginalTrait
{
    public function __construct(
        string $name = 'initial',
        int $counter = 1,
        protected ?\ArrayObject $bag = null,
    ) { $this->name = $name; $this->counter = $counter;
        $this->name = trim($this->name);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php80-promoted-property.php';
