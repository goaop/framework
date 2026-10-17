<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

/**
 * Woven by GreetingAspect, an aspect built by the application container
 */
class Greeter
{
    public function greet(string $name): string
    {
        return $name . '!';
    }
}
