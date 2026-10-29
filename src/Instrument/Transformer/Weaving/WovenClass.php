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

namespace Go\Instrument\Transformer\Weaving;

use Go\ParserReflection\ReflectionClass;

/**
 * A class-like converted to a trait, with the code of its proxy waiting to be saved
 *
 * Proxies are saved once the whole file is woven: their file names depend on the number of woven classes.
 *
 * @internal
 */
final readonly class WovenClass
{
    public function __construct(
        /** Reflection of the original class-like */
        public ReflectionClass $class,
        /** Code of the proxy, without the opening tag */
        public string $proxyCode,
        /** Position of the last token of the declaration, the include of the proxy goes after it */
        public int $lastTokenPosition,
    ) {}
}
