<?php

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTest;
use Go\Tests\TestProject\Application\Main;

class CacheWarmupCommandTest extends BaseFunctionalTest
{
    public function setUp()
    {
        $this->loadConfiguration();
        $this->clearCache();
    }

    public function testItWarmsUpCache()
    {
        $this->assertFalse(file_exists($this->configuration['cacheDir']));

        $this->warmUp();

        $this->assertClassIsWoven(Main::class);
    }
}
