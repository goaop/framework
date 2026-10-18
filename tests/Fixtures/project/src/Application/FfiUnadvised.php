<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * No advisor matches this class: the ffi driver records it as unadvised and never generates a donor
 */
final class FfiUnadvised
{
    public function ping(): string
    {
        return 'pong';
    }
}
