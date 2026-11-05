<?php

declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

/**
 * Typed properties without default value, read and written while uninitialized, null and holding a value (#819)
 */
final class PropertyStateDemo
{
    public ?string $nullable;

    public string $plain;

    public ?array $list;

    /**
     * @return list<mixed>
     */
    public function run(): array
    {
        $results   = [];
        $results[] = $this->nullable; // uninitialized: the advice supplies a value
        $this->nullable = null;
        $results[] = $this->nullable;
        $this->nullable = null;
        $this->nullable = 'value';
        $results[] = $this->nullable;
        $this->nullable = 'nested'; // the advice reads the property during the write

        $results[] = $this->plain;
        $this->plain = 'plain';
        $results[] = $this->plain;

        $this->list = null;
        $results[] = $this->list;
        $this->list = [1];
        $this->list[] = 2;
        $results[] = $this->list;

        return $results;
    }
}
