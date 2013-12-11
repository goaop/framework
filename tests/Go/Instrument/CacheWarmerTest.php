<?php

namespace Go\Instrument;

use \PHPUnit_Framework_TestCase as TestCase;

class CacheWarmerTest extends TestCase
{
    public function testWarmUp()
    {
        $cacheWarmer = new CacheWarmer();
        $cacheWarmer->warmUp();
    }
}