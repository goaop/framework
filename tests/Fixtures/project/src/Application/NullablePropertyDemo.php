<?php

declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

/**
 * Nullable properties holding null are read and written through their property hooks
 */
final class NullablePropertyDemo
{
    public ?string $label = null;

    public ?string $note;

    public function describe(): string
    {
        $label      = $this->label;
        $this->note = null;
        $note       = $this->note;
        $this->label = 'set';

        return var_export($label, true) . ',' . var_export($note, true) . ',' . $this->label;
    }
}
