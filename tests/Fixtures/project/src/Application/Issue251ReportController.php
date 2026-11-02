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

use Go\Tests\TestProject\Generated\Issue251BaseController;
use Go\Tests\TestProject\Generated\Issue251Controller;

/**
 * Test fixture (goaop/parser-reflection#251): its parent class and interface are declared at runtime, Composer can't
 * locate their source, so the weaver reflects the class partially
 */
class Issue251ReportController extends Issue251BaseController implements Issue251Controller
{
    public function report(): string
    {
        return 'report of ' . $this->handle();
    }
}
