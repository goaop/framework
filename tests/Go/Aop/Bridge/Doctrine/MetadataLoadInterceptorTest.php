<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Aop\Bridge\Doctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Go\Bridge\Doctrine\MetadataLoadInterceptor;
use Go\Core\AspectContainer;
use PHPUnit\Framework\TestCase;

class MetadataLoadInterceptorTest extends TestCase
{
    public function testItWillNotModifyClassMetadataForNonProxiedClasses(): void
    {
        $metadatas = [
            new ClassMetadata('\\Some\\Class\\Name'),
            new ClassMetadata(sprintf('%s\\Some\\Class\\Name', AspectContainer::AOP_PROXIED_SUFFIX)),
            new ClassMetadata(AspectContainer::AOP_PROXIED_SUFFIX),
        ];

        $metadataInterceptor = new MetadataLoadInterceptor();
        $entityManager = $this->createMock(EntityManager::class);

        /**
         * @var ClassMetadata $metadata
         */
        foreach ($metadatas as $metadata) {
            $metadata->isMappedSuperclass = false;
            $metadataInterceptor->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $entityManager));

            $this->assertFalse($metadata->isMappedSuperclass);
        }
    }

    public function testItWillModifyClassMetadataForNonProxiedClasses(): void
    {
        $metadata = new ClassMetadata(Entity__AopProxied::class);
        $metadataInterceptor = new MetadataLoadInterceptor();
        $entityManager = $this->createMock(EntityManager::class);

        $metadata->isMappedSuperclass = false;
        $metadata->isEmbeddedClass = true;
        $metadata->table = ['table_name'];
        $metadata->customRepositoryClassName = 'CustomRepositoryClass';

        $metadata->columnNames['mappedField'] = 'mapped_field';
        $metadata->fieldNames['mapped_field'] = 'mappedField';

        // Support both Doctrine ORM 2.x (array) and 3.x (FieldMapping object)
        if (class_exists(\Doctrine\ORM\Mapping\FieldMapping::class)) {
            $metadata->fieldMappings['mappedField'] = new \Doctrine\ORM\Mapping\FieldMapping(
                type: 'string',
                fieldName: 'mappedField',
                columnName: 'mapped_field',
            );
        } else {
            $metadata->fieldMappings['mappedField'] = ['columnName' => 'mapped_field', 'fieldName' => 'mappedField'];
        }

        $metadataInterceptor->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $entityManager));

        $this->assertTrue($metadata->isMappedSuperclass);
        $this->assertFalse($metadata->isEmbeddedClass);
        $this->assertEquals(0, count($metadata->table));
        $this->assertNull($metadata->customRepositoryClassName);

        $this->assertFalse(isset($metadata->fieldMappings['mappedField']));
        $this->assertFalse(isset($metadata->fieldNames['mapped_field']));
        $this->assertFalse(isset($metadata->columnNames['mappedField']));
    }
}

trait SimpleTrait
{
    private $mappedField;
}

class Entity__AopProxied
{
    use SimpleTrait;
}
