<?php

namespace Go\Functional;

class Issue293Test extends BaseFunctionalTest
{
    public function setUp()
    {
        self::warmUp();
    }

    /**
     * test for https://github.com/goaop/framework/issues/293
     */
    public function testItDoesNotWeaveDynamicMethodsForComplexStaticPointcut()
    {
        // it weaves Issue293StaticMembers class
        $this->assertTrue(file_exists(self::$aspectCacheDir.'/_proxies/src/Application/Issue293StaticMembers.php'));
        $this->assertTrue(file_exists(self::$aspectCacheDir.'/src/Application/Issue293StaticMembers.php'));

        // it does not weaves Issue293DynamicMembers class
        $this->assertFalse(file_exists(self::$aspectCacheDir.'/_proxies/src/Application/Issue293DynamicMembers.php'));
        $this->assertFalse(file_exists(self::$aspectCacheDir.'/src/Application/Issue293DynamicMembers.php'));
    }
}
