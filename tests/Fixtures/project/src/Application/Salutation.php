<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

/**
 * Service of the application container, injected into GreetingAspect
 */
final class Salutation
{
    public function __construct(public readonly string $text) {}
}
