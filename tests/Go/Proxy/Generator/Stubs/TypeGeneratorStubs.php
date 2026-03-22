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

namespace Go\Proxy\Generator\Stubs;

use Countable;
use Exception;
use Iterator;

function typeGenHelper_namedInt(int $x): void {}
function typeGenHelper_intersection(Countable&Iterator $x): void {}
function typeGenHelper_namedString(string $x): void {}
function typeGenHelper_namedFloat(float $x): void {}
function typeGenHelper_namedBool(bool $x): void {}
function typeGenHelper_namedArray(array $x): void {}
function typeGenHelper_namedCallable(callable $x): void {}
function typeGenHelper_namedMixed(mixed $x): void {}
function typeGenHelper_namedObject(object $x): void {}
function typeGenHelper_namedClass(Exception $x): void {}
function typeGenHelper_nullable(?string $x): void {}
function typeGenHelper_nullableClass(?Exception $x): void {}
function typeGenHelper_union(int|string $x): void {}
function typeGenHelper_unionWithNull(int|null $x): void {}
function typeGenHelper_voidReturn(): void {}
function typeGenHelper_nullReturn(): ?string { return null; }
