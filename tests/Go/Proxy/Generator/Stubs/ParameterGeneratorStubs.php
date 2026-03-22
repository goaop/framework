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

use Exception;

function paramGenHelper_simple(string $name, int $count = 0): void {}
function paramGenHelper_nullable(?string $name = null): void {}
function paramGenHelper_byRef(array &$data): void {}
function paramGenHelper_variadic(string ...$items): void {}
function paramGenHelper_variadicByRef(int &...$nums): void {}
function paramGenHelper_classType(Exception $ex): void {}
function paramGenHelper_noType($x): void {}
function paramGenHelper_sensitiveParam(#[\SensitiveParameter] string $secret): void {}
function paramGenHelper_noAttrParam(string $name): void {}
