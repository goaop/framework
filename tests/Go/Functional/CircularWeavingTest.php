<?php

namespace Go\Functional;

class CircularWeavingTest extends BaseFunctionalTest
{
    public function testCircularWeaving()
    {
        self::clearCache();
        $output = self::exec('cache:warmup:aop', '', 'circular_weaving', false);
        $this->assertContains('is not processed correctly due to detected circular weaving.', $output);
    }
}
