<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * No advisor matches this class: the z-engine driver records it as unadvised and never generates a donor
 */
final class ZEngineUnadvised
{
    public function ping(): string
    {
        return 'pong';
    }
}
