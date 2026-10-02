<?php
declare(strict_types=1);

/**
 * Boots the fixture project with the AOP kernel, builds a Doctrine EntityManager whose attribute driver
 * discovers entities through WovenEntityClassLocator and prints what Doctrine sees as JSON (issue #671).
 * No database connection is opened: metadata, schema SQL and hydration work without one.
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Go\Aop\Proxy;
use Go\Bridge\Doctrine\MetadataLoadInterceptor;
use Go\Bridge\Doctrine\WovenEntityClassLocator;
use Go\Tests\TestProject\Entity\WovenEntity;

include __DIR__ . '/../web/index.php';

$configuration = new Configuration();
$configuration->setMetadataDriverImpl(
    new AttributeDriver(WovenEntityClassLocator::createFromDirectories([__DIR__ . '/../src/Entity'])),
);
$configuration->enableNativeLazyObjects(true);

$connection    = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
$entityManager = new EntityManager($connection, $configuration);
$entityManager->getEventManager()->addEventListener(Events::loadClassMetadata, new MetadataLoadInterceptor());

$allMetadata = $entityManager->getMetadataFactory()->getAllMetadata();
$metadata    = $entityManager->getClassMetadata(WovenEntity::class);

ob_start();
$entity = $entityManager->getUnitOfWork()->createEntity(WovenEntity::class, ['id' => 7, 'name' => 'loaded', 'counter' => 3]);
$hydrationOutput = ob_get_clean();
assert($entity instanceof WovenEntity);

ob_start();
$name = $entity->name;
$readOutput = ob_get_clean();

echo json_encode([
    'entities'        => array_map(static fn($classMetadata): string => $classMetadata->getName(), $allMetadata),
    'woven'           => is_a(WovenEntity::class, Proxy::class, true),
    'fields'          => array_keys($metadata->fieldMappings),
    'callbacks'       => $metadata->lifecycleCallbacks,
    'table'           => $metadata->getTableName(),
    'schema'          => new SchemaTool($entityManager)->getCreateSchemaSql($allMetadata),
    'hydrationOutput' => $hydrationOutput,
    'name'            => $name,
    'readOutput'      => $readOutput,
    'counter'         => $entity->getCounter(),
], JSON_THROW_ON_ERROR);
