<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Generator;

/**
 * Common contract for all top-level code generators (class, trait, file, etc.).
 */
interface GeneratorInterface
{
    /**
     * Returns the name of the generated declaration (class/trait/function name).
     */
    public function getName(): string;

    /**
     * Returns the generated PHP source code as a string.
     */
    public function generate(): string;
}
