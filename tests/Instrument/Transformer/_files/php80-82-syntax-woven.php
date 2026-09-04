<?php
declare(strict_types=1);
namespace Test\ns1;

enum SyntaxPriority: int
{
    case Low = 1;
    case High = 2;
}

/**
 * Compact class covering general PHP 8.0-8.3 syntax through the weaver:
 * constructor promotion (non-intercepted property), new-in-initializer parameter
 * default, named arguments, match expression, nullsafe operator, enum usage,
 * readonly property, first-class callable and a typed class constant.
 */
trait TestPhp80To82SyntaxClassOriginalTrait
{
    public const int LIMIT = 10;

    public readonly float $ratio;

    public function __construct(
        private string $label = 'default',
        private \ArrayObject $items = new \ArrayObject([1, 2, 3]),
    ) {
        $this->ratio = \round(num: 0.5, precision: 1);
    }

    public function describe(?\ArrayObject $extra = null): string
    {
        $lengthOf = \strlen(...);
        $count    = $extra?->count() ?? $this->items->count();

        return match (true) {
            $count >= self::LIMIT                    => 'huge:' . $lengthOf($this->label),
            $count >= SyntaxPriority::High->value    => 'several',
            default                                  => 'few',
        };
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php80-82-syntax.php';
