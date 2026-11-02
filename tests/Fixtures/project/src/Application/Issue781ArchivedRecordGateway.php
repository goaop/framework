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
 * Test fixture (issue #781): woven grandchild, the inherited method is already intercepted by its woven parent
 */
class Issue781ArchivedRecordGateway extends Issue781RecordGateway {}
