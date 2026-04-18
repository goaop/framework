<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.1 backed enum — woven by extracting methods into a trait and re-declaring cases in the proxy enum.
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
