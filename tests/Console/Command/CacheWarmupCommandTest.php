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
use Go\Tests\TestProject\Application\Main;

class CacheWarmupCommandTest extends BaseFunctionalTestCase
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
