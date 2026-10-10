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

namespace Go\Instrument\ZEngine;

/**
 * What the z-engine driver did with a loaded class
 */
enum WeaveOutcome: string
{
    /** Advised methods were rewired through the engine */
    case Woven = 'woven';

    /** No advisor matched the class, nothing to do */
    case NoAdvices = 'no-advices';

    /** The class was woven by an earlier call in this request */
    case AlreadyWoven = 'already-woven';

    /** The class is not weavable by this driver (see WeaveResult::$reason) */
    case Skipped = 'skipped';
}
