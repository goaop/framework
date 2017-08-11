<?php
declare(strict_types = 1);

namespace Go\Console\Command;

use Go\Functional\BaseFunctionalTest;

class CacheWarmupCommandTest extends BaseFunctionalTest
{
    public function setUp()
    {
        self::clearCache();
    }

    public function testItWarmsUpCache()
    {
        $this->assertFalse(file_exists(self::$aspectCacheDir));

        self::warmUp();

        $this->assertTrue(file_exists(self::$aspectCacheDir.'/_proxies/src/Application/Main.php'));
        $this->assertTrue(file_exists(self::$aspectCacheDir.'/src/Application/Main.php'));
    }
}
