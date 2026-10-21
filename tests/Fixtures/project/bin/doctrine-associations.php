<?php
declare(strict_types=1);

/**
 * Boots the fixture project with the AOP kernel and runs two associated woven entities (bidirectional
 * one-to-many / many-to-one) through Doctrine ORM against an in-memory SQLite database: schema, persist,
 * a DQL join, lazy loading of the many-to-one side and of the collection. Prints what Doctrine sees and
 * the SQL it executed as JSON (issue #327).
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Go\Aop\Proxy;
use Go\Bridge\Doctrine\MetadataLoadInterceptor;
use Go\Bridge\Doctrine\WovenEntityClassLocator;
use Go\Tests\TestProject\Entity\Sensor;
use Go\Tests\TestProject\Entity\SensorValue;
use Psr\Log\AbstractLogger;

include __DIR__ . '/../web/index.php';

$logger = new class extends AbstractLogger {
    /** @var list<string> Executed SQL statements */
    public array $queries = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (is_string($context['sql'] ?? null)) {
            $this->queries[] = $context['sql'];
        }
    }
};

$configuration = new Configuration();
$configuration->setMetadataDriverImpl(
    new AttributeDriver(WovenEntityClassLocator::createFromDirectories([__DIR__ . '/../src/Entity'])),
);
$configuration->enableNativeLazyObjects(true);
$configuration->setMiddlewares([new Middleware($logger)]);

$connection    = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
$entityManager = new EntityManager($connection, $configuration);
$entityManager->getEventManager()->addEventListener(Events::loadClassMetadata, new MetadataLoadInterceptor());

$sensorMetadata = $entityManager->getClassMetadata(Sensor::class);
$valueMetadata  = $entityManager->getClassMetadata(SensorValue::class);
new SchemaTool($entityManager)->createSchema([$sensorMetadata, $valueMetadata]);

ob_start();
$sensor = new Sensor('thermometer');
$sensor->addValue(20);
$sensor->addValue(25);
$entityManager->persist($sensor);
$entityManager->flush();
$sensorId = $sensor->getId();
$valueIds = array_map(static fn(SensorValue $value): ?int => $value->getId(), $sensor->getValues()->toArray());
$entityManager->clear();
$persistOutput = ob_get_clean();

// DQL join over the association, both sides hydrated at once
ob_start();
$joined = $entityManager->createQuery(
    'SELECT s, v FROM ' . Sensor::class . ' s JOIN s.values v WHERE s.id = :id ORDER BY v.value ASC',
)->setParameter('id', $sensorId)->getResult();
assert(is_array($joined) && count($joined) === 1 && $joined[0] instanceof Sensor);
$joinedSensor = $joined[0];
$joinedValues = array_map(static fn(SensorValue $value): int => $value->getValue(), $joinedSensor->getValues()->toArray());
$entityManager->clear();
$joinOutput = ob_get_clean();

// Lazy loading of the many-to-one side: a native lazy ghost of the woven class
ob_start();
$value = $entityManager->find(SensorValue::class, $valueIds[0]);
assert($value instanceof SensorValue);
$lazySensor            = $value->getSensor();
$lazySensorClass       = $lazySensor::class;
$lazySensorUninitialized = $entityManager->isUninitializedObject($lazySensor);
$lazySensorName        = $lazySensor->getName();
$lazySensorInitialized = !$entityManager->isUninitializedObject($lazySensor);
$entityManager->clear();
$lazyOutput = ob_get_clean();

// Lazy loading of the collection side
$queriesBeforeCollection = count($logger->queries);
ob_start();
$foundSensor = $entityManager->find(Sensor::class, $sensorId);
assert($foundSensor instanceof Sensor);
$collectionCount = $foundSensor->getValues()->count();
$collectionOutput = ob_get_clean();
$collectionLoadQueries = array_slice($logger->queries, $queriesBeforeCollection);

echo json_encode([
    'woven'            => [
        Sensor::class      => is_a(Sensor::class, Proxy::class, true),
        SensorValue::class => is_a(SensorValue::class, Proxy::class, true),
    ],
    'tables'           => [$sensorMetadata->getTableName(), $valueMetadata->getTableName()],
    'targets'          => [
        'Sensor.values'     => $sensorMetadata->getAssociationTargetClass('values'),
        'SensorValue.sensor' => $valueMetadata->getAssociationTargetClass('sensor'),
    ],
    'mappedSuperclass' => [$sensorMetadata->isMappedSuperclass, $valueMetadata->isMappedSuperclass],
    'persistOutput'    => $persistOutput,
    'joinedValues'     => $joinedValues,
    'joinOutput'       => $joinOutput,
    'lazySensor'       => [
        'class'         => $lazySensorClass,
        'uninitialized' => $lazySensorUninitialized,
        'name'          => $lazySensorName,
        'initialized'   => $lazySensorInitialized,
    ],
    'lazyOutput'       => $lazyOutput,
    'collectionCount'  => $collectionCount,
    'collectionOutput' => $collectionOutput,
    'collectionLoadQueries' => $collectionLoadQueries,
    'queries'          => $logger->queries,
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
