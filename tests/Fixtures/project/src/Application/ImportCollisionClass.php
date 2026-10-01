<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

use Go\Tests\TestProject\Log\Interceptor;

/**
 * Imports a class named like the Interceptor facade of generated proxies and is advised by
 * two aspects that share one short name (issue #668)
 */
class ImportCollisionClass
{
    public function write(string $message, string $prefix = Interceptor::PREFIX): string
    {
        return $prefix . $message;
    }
}
