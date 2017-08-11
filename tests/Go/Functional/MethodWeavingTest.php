<?php
declare(strict_types=1);

namespace Go\Functional;

class MethodWeavingTest extends BaseFunctionalTest
{
    public function setUp()
    {
        self::warmUp();
    }

    /**
     * test for https://github.com/goaop/framework/issues/335
     */
    public function testItDoesNotWeaveAbstractMethods()
    {
        // it weaves Main class
        $this->assertTrue(file_exists(self::$aspectCacheDir.'/_proxies/src/Application/Main.php'));
        $this->assertTrue(file_exists(self::$aspectCacheDir.'/src/Application/Main.php'));

        // it does not weaves AbstractBar class
        $this->assertFalse(file_exists(self::$aspectCacheDir.'/_proxies/src/Application/AbstractBar.php'));
        $this->assertFalse(file_exists(self::$aspectCacheDir.'/src/Application/AbstractBar.php'));
    }
}
