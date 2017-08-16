<?php

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTest;

class DebugAspectCommandTest extends BaseFunctionalTest
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
            $this->assertContains($string, $output);
        }
    }
}
