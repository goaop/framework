<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTestCase;

class DebugAspectCommandTest extends BaseFunctionalTestCase
{
    public function testItDisplaysAspectsDebugInfo()
    {
        $output = $this->execute('debug:aspect');

        $expected = [
            'Go\Tests\TestProject\Kernel\DefaultAspectKernel has following enabled aspects',
            'Go\Tests\TestProject\Aspect\LoggingAspect',
            'Go\Tests\TestProject\Aspect\LoggingAspect->beforeMethod'
        ];

        foreach ($expected as $string) {
            $this->assertStringContainsString($string, $output);
        }
    }
}
