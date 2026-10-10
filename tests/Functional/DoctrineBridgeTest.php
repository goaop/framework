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

namespace Go\Functional;

use Go\Tests\TestProject\Entity\WovenEntity;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Doctrine ORM sees a woven entity exactly once, with every mapping, when entities are discovered
 * through WovenEntityClassLocator (issue #671)
 */
class DoctrineBridgeTest extends BaseFunctionalTestCase
{
    public function testDoctrineMapsWovenEntity(): void
    {
        $this->assertClassIsWoven(WovenEntity::class);
        $this->assertPropertyWoven(
            WovenEntity::class,
            'name',
            'Go\\Tests\\TestProject\\Aspect\\EntityFieldAspect->beforeNameAccess',
        );

        $phpExecutable = (new PhpExecutableFinder())->find();
        $this->assertIsString($phpExecutable);
        $process = new Process(
            [$phpExecutable, ...$this->getPhpOptions(), __DIR__ . '/../Fixtures/project/bin/doctrine-metadata.php'],
            null,
            ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()],
        );
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($result);

        // Discovered through the autoloader, so the woven class is mapped (not the original source)
        $this->assertSame([WovenEntity::class], $result['entities']);
        $this->assertTrue($result['woven']);
        $this->assertEqualsCanonicalizing(['id', 'name', 'counter'], $result['fields']);
        $this->assertSame(['prePersist' => ['beforePersist']], $result['callbacks']);
        $this->assertSame('woven_entity', $result['table']);
        $this->assertIsArray($result['schema']);
        $this->assertCount(1, $result['schema']);
        $createTable = $result['schema'][0];
        $this->assertIsString($createTable);
        $this->assertStringContainsString('name VARCHAR(64) NOT NULL', $createTable);
        $this->assertStringContainsString('counter INTEGER NOT NULL', $createTable);

        // Hydration writes the raw property value (no advice), user code goes through the aspect
        $this->assertSame('', $result['hydrationOutput']);
        $this->assertSame('loaded', $result['name']);
        $this->assertSame('name;', $result['readOutput']);
        $this->assertSame(3, $result['counter']);
    }

    protected function getConfigurationName(): string
    {
        return 'default';
    }
}
