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

namespace Go\Aop\Framework;

/**
 * Frame hooks for test joinpoints that are never invoked through enterFrame()/leaveFrame()
 */
trait StatelessFramesTrait
{
    /**
     * @return array<mixed>
     */
    protected function saveFrame(): array
    {
        return [];
    }

    /**
     * @param array<mixed> $frame
     */
    protected function restoreFrame(array $frame): void {}

    protected function releaseFrame(): void {}
}
