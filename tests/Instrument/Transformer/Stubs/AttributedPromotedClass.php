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

namespace Go\Instrument\Transformer\Stubs;

/**
 * Attributes around a promoted constructor property (issue #599).
 *
 * Both the constructor itself and the promoted parameter carry attribute groups whose
 * arguments contain nested brackets. WeavingTransformer has to skip those groups as a
 * whole while demoting the promoted property and while locating the constructor body
 * brace for the injected assignment.
 */
class AttributedPromotedClass
{
    #[MarkerAttribute([1, 2])]
    public function __construct(
        #[MarkerAttribute(['a' => ['b']])]
        private string $name = 'initial',
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }
}
