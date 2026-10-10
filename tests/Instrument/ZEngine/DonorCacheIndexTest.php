<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ZEngine;

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Exception\WeavingException;
use Go\Aop\WeavingDriver;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\PhpUnit\UsesTemporaryDirectory;
use PHPUnit\Framework\TestCase;

/**
 * The donor index of the z-engine driver: records per class, freshness through the shared rule, persisted as PHP
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class DonorCacheIndexTest extends TestCase
{
    use UsesTemporaryDirectory;

    private const string CLASS_NAME = 'App\Service';

    private string $appDir;

    private string $cacheDir;

    private string $source;

    protected function setUp(): void
    {
        // Real directories: the index file is loaded with include, its paths are relative to __DIR__
        $this->appDir   = self::createTemporaryDirectory('donor-app');
        $this->cacheDir = self::createTemporaryDirectory('donor-cache');
        $this->source   = $this->appDir . '/src/Service.php';
        mkdir($this->appDir . '/src');
        file_put_contents($this->source, '<?php class Service {}');
    }

    protected function tearDown(): void
    {
        self::removeTemporaryDirectory($this->cacheDir);
        self::removeTemporaryDirectory($this->appDir);
    }

    public function testDonorFilesMirrorTheApplicationTreeBelowTheDonorDirectory(): void
    {
        $index = $this->createIndex();

        $this->assertSame(
            $this->cacheDir . '/_zengine/src/Service__AopDonor.php',
            $index->donorFileFor($this->source, 'Service'),
        );
        $this->assertSame(
            $this->cacheDir . '/_zengine/Root__AopDonor.php',
            $index->donorFileFor($this->appDir . '/Root.php', 'Root'),
        );
        // A source outside the application root is never woven, so it has no donor file
        $this->assertTrue($index->isApplicationSource($this->source));
        $this->assertFalse($index->isApplicationSource($this->cacheDir . '/Outside.php'));
        $this->expectException(WeavingException::class);
        $index->donorFileFor($this->cacheDir . '/Outside.php', 'Outside');
    }

    public function testRecordsSurviveAFlushAndAreServedWhileFresh(): void
    {
        $donorFile = $this->cacheDir . '/_zengine/src/Service__AopDonor.php';
        mkdir(dirname($donorFile), 0777, true);
        file_put_contents($donorFile, '<?php abstract class Service__AopDonor {}');

        $writer = $this->createIndex();
        $this->assertNull($writer->findFresh(self::CLASS_NAME, $this->source, $this->createContainer()));
        $writer->recordDonor(self::CLASS_NAME, $this->source, $donorFile, 'App\Service__AopDonor', ['run']);
        $writer->recordNoAdvices('App\Plain', $this->source);
        // Records of the request are served before the flush...
        $this->assertSame(
            ['donor' => $donorFile, 'donorClass' => 'App\Service__AopDonor', 'methods' => ['run']],
            $writer->findFresh(self::CLASS_NAME, $this->source, $this->createContainer()),
        );
        $writer->flush();
        $this->assertFileExists($this->cacheDir . '/_zengine.cache');

        // ...and by another request through the index file
        $reader = $this->createIndex();
        $this->assertSame(
            ['donor' => $donorFile, 'donorClass' => 'App\Service__AopDonor', 'methods' => ['run']],
            $reader->findFresh(self::CLASS_NAME, $this->source, $this->createContainer()),
        );
        $this->assertSame(
            ['donor' => null, 'donorClass' => null, 'methods' => []],
            $reader->findFresh('App\Plain', $this->source, $this->createContainer()),
        );
        $this->assertNull($reader->findFresh('App\Unknown', $this->source, $this->createContainer()));
    }

    public function testAStaleOrOrphanedRecordIsAMiss(): void
    {
        $donorFile = $this->cacheDir . '/_zengine/src/Service__AopDonor.php';
        mkdir(dirname($donorFile), 0777, true);
        file_put_contents($donorFile, '<?php abstract class Service__AopDonor {}');
        $writer = $this->createIndex();
        $writer->recordDonor(self::CLASS_NAME, $this->source, $donorFile, 'App\Service__AopDonor', ['run']);
        $writer->flush();

        // The container knows a resource newer than the record
        $this->assertNull($this->createIndex()->findFresh(self::CLASS_NAME, $this->source, $this->createContainer(false)));

        // The source changed since the record was written
        $mtime = filemtime($this->source);
        $this->assertNotFalse($mtime);
        touch($this->source, $mtime - 60);
        $this->assertNull($this->createIndex()->findFresh(self::CLASS_NAME, $this->source, $this->createContainer()));
        touch($this->source, $mtime);
        $this->assertNotNull($this->createIndex()->findFresh(self::CLASS_NAME, $this->source, $this->createContainer()));

        // The donor file is gone
        unlink($donorFile);
        $this->assertNull($this->createIndex()->findFresh(self::CLASS_NAME, $this->source, $this->createContainer()));
    }

    public function testAnIndexOfAnotherFormatVersionIsIgnored(): void
    {
        file_put_contents($this->cacheDir . '/_zengine.cache', '<?php return ["version" => 0, "classes" => ["App\\Service" => ["donor" => null]]];');

        $this->assertNull($this->createIndex()->findFresh(self::CLASS_NAME, $this->source, $this->createContainer()));
    }

    public function testWithoutCacheDirectoryNothingIsPersisted(): void
    {
        $index = $this->createIndex(null);
        $index->recordNoAdvices(self::CLASS_NAME, $this->source);
        $index->flush();

        $this->assertSame(
            ['donor' => null, 'donorClass' => null, 'methods' => []],
            $index->findFresh(self::CLASS_NAME, $this->source, $this->createContainer()),
            'Records of the request are still served in memory',
        );

        $this->expectException(InvalidConfigurationException::class);
        $index->donorFileFor($this->source, 'Service');
    }

    private function createIndex(?string $cacheDir = ''): DonorCacheIndex
    {
        $options = [
            'debug'          => true,
            'appDir'         => $this->appDir,
            'cacheDir'       => $cacheDir === '' ? $this->cacheDir : $cacheDir,
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [],
            'excludePaths'   => [],
            'containerClass' => Container::class,
            'driver'         => WeavingDriver::ZEngine,
        ];
        $kernel = $this->createMock(AspectKernel::class);
        $kernel->method('getOptions')->willReturn($options);
        $kernel->method('hasFeature')->willReturn(false);

        return new DonorCacheIndex(new CachePathManager($kernel), $options);
    }

    private function createContainer(bool $fresh = true): AspectContainer
    {
        $container = $this->createMock(AspectContainer::class);
        $container->method('isFreshSince')->willReturn($fresh);

        return $container;
    }
}
