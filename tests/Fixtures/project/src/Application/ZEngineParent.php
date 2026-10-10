<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Parent of the z-engine driver fixtures: only greet() is advised here, the rest is advised through ZEngineChild
 */
class ZEngineParent
{
    public function greet(string $name): string
    {
        return 'Hello, ' . $name;
    }

    public function describe(): self
    {
        return $this;
    }

    public static function create(string $name): static
    {
        $instance = new static();
        $instance->greet($name);

        return $instance;
    }

    public function callSecret(): string
    {
        return $this->secret();
    }

    protected function secret(): string
    {
        return 'secret of ' . static::class;
    }
}
