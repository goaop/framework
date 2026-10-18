<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Driver\StreamWrapper\Functional;

use Go\Functional\MagicConstantTest;

/**
 * Magic constants resolve to the original location with the stream wrapper driver too: a woven file is served
 * from memory under its original path, an untransformed one by its native handle
 */
final class StreamWrapperMagicConstantTest extends MagicConstantTest
{
    protected function getConfigurationName(): string
    {
        return 'stream_wrapper';
    }
}
