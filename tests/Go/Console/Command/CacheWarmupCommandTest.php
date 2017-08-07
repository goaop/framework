<?php
declare(strict_types = 1);

namespace Go\Console\Command;

use PHPUnit_Framework_TestCase as TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class CacheWarmupCommandTest extends TestCase
{
    protected $aspectCacheDir = __DIR__.'/../../../Fixtures/project/var/cache/aspect';

    public function setUp()
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($this->aspectCacheDir)) {
            $filesystem->remove($this->aspectCacheDir);
        }
    }

    public function testItWarmsUpCache()
    {
        $this->assertFalse(file_exists($this->aspectCacheDir));

        $process = new Process(sprintf('php %s cache:warmup:aop %s',
            realpath(__DIR__.'/../../../Fixtures/project/bin/console'),
            realpath(__DIR__.'/../../../Fixtures/project/web/index.php')
        ));

        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Unable to execute "cache:warmup:aop" command.');

        $this->assertTrue(file_exists($this->aspectCacheDir.'/_proxies/src/Application/Main.php'));
        $this->assertTrue(file_exists($this->aspectCacheDir.'/src/Application/Main.php'));
    }
}
