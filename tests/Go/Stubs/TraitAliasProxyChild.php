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
 * Subclass of TraitAliasProxy used to verify that StaticTraitAliasMethodInvocation
 * correctly handles late static binding (LSB): the joinpoint is registered on the
 * parent proxy class but invoked with the subclass as the static scope.
 */
class TraitAliasProxyChild extends TraitAliasProxy {}
