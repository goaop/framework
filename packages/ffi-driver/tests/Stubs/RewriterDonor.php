<?php

declare(strict_types=1);

namespace Go\Driver\Ffi\Stubs;

/**
 * A donor without the method the rewriter is asked for: the engine refuses the rewiring
 */
abstract class RewriterDonor
{
    public function unrelated(): void {}
}
