<?php

declare(strict_types=1);

namespace Go\Instrument\Transformer\Stubs;

use Go\Stubs\Collision\The as Level;

/**
 * Parent class whose method signature resolves `Level` against the imports of THIS file (#826)
 */
class ImportedDefaultsBase
{
    public function log(string $level = Level::LEVEL, ?Level $target = null): string
    {
        return $level;
    }
}
