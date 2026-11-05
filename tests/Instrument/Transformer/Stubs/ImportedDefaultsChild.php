<?php

declare(strict_types=1);

namespace Go\Instrument\Transformer\Stubs;

use Go\Stubs\Collision\Interceptor as Level;

/**
 * Child class binding `Level` to another class than its parent's file: the proxy overriding the inherited method must
 * keep the meaning of the parent's signature and default value (#826)
 */
class ImportedDefaultsChild extends ImportedDefaultsBase
{
    public function own(?Level $interceptor = null): ?Level
    {
        return $interceptor;
    }
}
