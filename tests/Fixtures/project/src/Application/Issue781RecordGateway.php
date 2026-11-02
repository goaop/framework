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

namespace Go\Tests\TestProject\Application;

/**
 * Test fixture (issue #781): woven child of a parent that is not woven, intercepts the inherited method itself
 */
class Issue781RecordGateway extends Issue781PlainRecord {}
