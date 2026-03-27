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

namespace Go\Stubs;

/**
 * Test stub: a class that uses TraitAliasProxied (gets "inherited" trait methods)
 * plus its own directly declared methods. Used by ClassProxyGeneratorTest to verify
 * that the proxy generator handles methods from both sources correctly.
 */
class ClassWithMixedSources
{
    use TraitAliasProxied;

    public function ownPublicMethod(): int
    {
        return 42;
    }

    private function ownPrivateMethod(): string
    {
        return 'private';
    }
}
