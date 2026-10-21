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

use Go\Tests\TestProject\Entity\Sensor;
use Go\Tests\TestProject\Entity\SensorValue;
use Go\Tests\TestProject\Entity\WovenEntity;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Doctrine ORM sees a woven entity exactly once, with every mapping, when entities are discovered
 * through WovenEntityClassLocator (issue #671), and maps associations between woven entities (issue #327)
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

        $result = $this->runFixtureScript('doctrine-metadata.php');

        // Discovered through the autoloader, so the woven classes are mapped (not the original sources)
        $this->assertEqualsCanonicalizing([Sensor::class, SensorValue::class, WovenEntity::class], $result['entities']);
        $this->assertTrue($result['woven']);
        $this->assertEqualsCanonicalizing(['id', 'name', 'counter'], $result['fields']);
        $this->assertSame(['prePersist' => ['beforePersist']], $result['callbacks']);
        $this->assertSame('woven_entity', $result['table']);
        $this->assertIsArray($result['schema']);
        $createTables = array_values(array_filter(
            $result['schema'],
            static fn(mixed $sql): bool => is_string($sql) && str_starts_with($sql, 'CREATE TABLE woven_entity '),
        ));
        $this->assertCount(1, $createTables);
        $createTable = $createTables[0];
        $this->assertStringContainsString('name VARCHAR(64) NOT NULL', $createTable);
        $this->assertStringContainsString('counter INTEGER NOT NULL', $createTable);

        // Hydration writes the raw property value (no advice), user code goes through the aspect
        $this->assertSame('', $result['hydrationOutput']);
        $this->assertSame('loaded', $result['name']);
        $this->assertSame('name;', $result['readOutput']);
        $this->assertSame(3, $result['counter']);
    }

    /**
     * Under the 3.x extend-based engine the mapping attributes ended up on the renamed parent class
     * (`Entity__AopProxied`): the table name got the suffix, every association pointed at the parent,
     * joins were built with mismatched aliases and lazy loading was broken (issue #327). The trait-based
     * engine keeps the mapping on the woven class itself, so a bidirectional association between two
     * woven entities works as it does without weaving.
     */
    public function testDoctrineMapsAssociationsBetweenWovenEntities(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required to run queries against SQLite');
        }
        $this->assertClassIsWoven(Sensor::class);
        $this->assertClassIsWoven(SensorValue::class);

        $result = $this->runFixtureScript('doctrine-associations.php');

        $this->assertSame([Sensor::class => true, SensorValue::class => true], $result['woven']);
        $this->assertSame(['sensor', 'sensor_value'], $result['tables']);
        $this->assertSame(
            ['Sensor.values' => SensorValue::class, 'SensorValue.sensor' => Sensor::class],
            $result['targets'],
        );
        $this->assertSame([false, false], $result['mappedSuperclass']);

        // Entity methods called by user code are advised, including the constructors
        $this->assertSame('__construct;addValue;__construct;addValue;__construct;getId;getValues;getId;getId;', $result['persistOutput']);

        // The DQL join hydrates both sides without running advices; reading the result does
        $this->assertSame([20, 25], $result['joinedValues']);
        $this->assertSame('getValues;getValue;getValue;', $result['joinOutput']);

        // The many-to-one side is a native lazy object of the woven class, loaded on first access
        $this->assertSame(
            ['class' => Sensor::class, 'uninitialized' => true, 'name' => 'thermometer', 'initialized' => true],
            $result['lazySensor'],
        );
        $this->assertSame('getSensor;getName;', $result['lazyOutput']);

        // The collection is loaded lazily with one query over the join column
        $this->assertSame(2, $result['collectionCount']);
        $this->assertSame('getValues;', $result['collectionOutput']);
        $this->assertIsArray($result['collectionLoadQueries']);
        $this->assertCount(2, $result['collectionLoadQueries']);
        $this->assertSame(
            'SELECT t0.id AS id_1, t0.value AS value_2, t0.sensor_id AS sensor_id_3 FROM sensor_value t0 WHERE t0.sensor_id = ?',
            $result['collectionLoadQueries'][1],
        );

        // Every SQL statement references the tables of the woven classes with consistent aliases
        $this->assertIsArray($result['queries']);
        $joinQuery = array_values(array_filter($result['queries'], static fn(mixed $sql): bool => is_string($sql) && str_contains($sql, 'INNER JOIN')));
        $this->assertCount(1, $joinQuery);
        $this->assertSame(
            'SELECT s0_.id AS id_0, s0_.name AS name_1, s1_.id AS id_2, s1_.value AS value_3, s1_.sensor_id AS sensor_id_4'
            . ' FROM sensor s0_ INNER JOIN sensor_value s1_ ON s0_.id = s1_.sensor_id WHERE s0_.id = ? ORDER BY s1_.value ASC',
            $joinQuery[0],
        );
        foreach ($result['queries'] as $sql) {
            $this->assertIsString($sql);
            $this->assertStringNotContainsString('aop', strtolower($sql));
        }
    }

    /**
     * Runs a script of the fixture project in a subprocess and decodes its JSON output
     *
     * @return array<string, mixed>
     */
    private function runFixtureScript(string $scriptName): array
    {
        $phpExecutable = (new PhpExecutableFinder())->find();
        $this->assertIsString($phpExecutable);
        $process = new Process(
            [$phpExecutable, __DIR__ . '/../Fixtures/project/bin/' . $scriptName],
            null,
            ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()],
        );
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($result);

        /** @var array<string, mixed> $result */
        return $result;
    }

    protected function getConfigurationName(): string
    {
        return 'default';
    }
}
