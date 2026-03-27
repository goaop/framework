<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.1 enum — must NOT be woven (enums cannot be converted to traits).
 */
enum TestStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match($this) {
            TestStatus::Active   => 'Active',
            TestStatus::Inactive => 'Inactive',
        };
    }
}
