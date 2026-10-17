<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Container;

use Closure;
use Psr\Container\ContainerInterface;

/**
 * Minimal PSR-11 container standing in for the container of an application (Symfony, Laravel, PHP-DI, ...)
 */
final class ApplicationContainer implements ContainerInterface
{
    /**
     * @var array<string, object> Services built so far, shared between lookups
     */
    private array $services = [];

    /**
     * @param array<string, Closure(): object> $factories
     */
    public function __construct(private readonly array $factories) {}

    public function get(string $id): object
    {
        if (!isset($this->factories[$id])) {
            throw new ApplicationServiceNotFoundException("Service $id is not defined in the application container");
        }

        return $this->services[$id] ??= ($this->factories[$id])();
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
