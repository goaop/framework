<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * PHP 8.1 backed enum — woven by extracting methods into a trait and re-declaring cases in the proxy enum.
 */
trait TestStatus__AopProxied
{



    public function label(): string
    {
        return match($this) {
            TestStatus::Active   => 'Active',
            TestStatus::Inactive => 'Inactive',
        };
    }
}
include_once AOP_CACHE_DIR . '/Transformer/_files/php81-enum.php';
