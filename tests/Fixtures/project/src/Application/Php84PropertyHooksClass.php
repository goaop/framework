<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

class Php84PropertyHooksClass
{
    public string $value = 'test';

    public protected(set) string $limited = 'limited';

    public string $plain = 'plain';

    public function readValue(): string
    {
        return $this->value;
    }
}
