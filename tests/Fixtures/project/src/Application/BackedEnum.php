<?php

declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

use Go\Tests\TestProject\Annotation\Loggable;

/**
 * Backed string enum used by EnumWeavingTest.
 *
 * Covers: backed type, interface, pre-included trait, class constant, PHP attribute (on the enum and on a case),
 * instance method, and static method — all combined in a single enum.
 */
#[Loggable]
enum BackedEnum: string implements EnumWithLabel
{
    use EnumExtraBehaviorTrait;

    /**
     * The status of an active record
     */
    #[Loggable]
    case Active   = 'active';
    case Inactive = 'inactive';

    public const STATUS_PREFIX = 'status:';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function doSomething(): string
    {
        return $this->value;
    }

    public static function doSomethingStatic(): string
    {
        return self::STATUS_PREFIX . 'static';
    }
}
