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

namespace Go\Bridge\Doctrine\Fixtures;

/**
 * The file name does not match the class name, so the autoloader can not load the class and the
 * locator must skip it instead of including the file
 */
final class NotAutoloadableEntity {}
