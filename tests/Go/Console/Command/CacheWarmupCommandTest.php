<?php
declare(strict_types = 1);

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTest;
use Go\Tests\TestProject\Application\Main;

class CacheWarmupCommandTest extends BaseFunctionalTest
{
    public function setUp(): void
    {
        $this->loadConfiguration();
        $this->clearCache();
    }

    public function testItWarmsUpCache()
    {
        $this->assertFileDoesNotExist($this->configuration['cacheDir']);

        $this->warmUp();

        $this->assertClassIsWoven(Main::class);
    }
}
