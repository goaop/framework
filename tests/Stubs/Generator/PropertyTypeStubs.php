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

namespace Go\Stubs\Generator;

use Countable;
use Iterator;

/**
 * Property type declarations covering the branches of
 * AbstractInterceptedPropertyGenerator's phpDoc type rendering
 * (renderTypeForPhpDoc()) and its array-typed union detection
 * (isArrayTypedProperty()) that plain scalar/array-typed properties
 * elsewhere in the test suite do not exercise.
 */
class PropertyTypeStubs
{
    /** Union type that includes 'array' among its members. */
    public array|int $unionArrayProp;

    /** No declared type at all. */
    public $untypedProp;

    /** Nullable named type. */
    public ?string $nullableProp = null;

    /** Pure intersection type. */
    public Countable&Iterator $intersectionProp;
}
