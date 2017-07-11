<?php
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

class MetadataLoadInterceptorTest extends \PHPUnit_Framework_TestCase
{
    public function testItWillNotModifyClassMetadataForNonProxiedClasses()
    {
        $metadatas = [
            new ClassMetadata('\\Some\\Class\\Name'),
            new ClassMetadata(sprintf('%s\\Some\\Class\\Name', AspectContainer::AOP_PROXIED_SUFFIX)),
            new ClassMetadata(AspectContainer::AOP_PROXIED_SUFFIX),
        ];

        $metadataInterceptor = new MetadataLoadInterceptor();
        $entityManager = $this->getMockBuilder(EntityManager::class)->disableOriginalConstructor()->getMock();

        /**
         * @var ClassMetadata $metadata
         */
        foreach ($metadatas as $metadata) {
            $metadata->isMappedSuperclass = false;
            $metadataInterceptor->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $entityManager));

            $this->assertFalse($metadata->isMappedSuperclass);
        }
    }

    public function testItWillModifyClassMetadataForNonProxiedClasses()
    {
        $metadata = new ClassMetadata(Entity__AopProxied::class);
        $metadataInterceptor = new MetadataLoadInterceptor();
        $entityManager = $this->getMockBuilder(EntityManager::class)->disableOriginalConstructor()->getMock();

        $metadata->isMappedSuperclass = false;
        $metadata->isEmbeddedClass = true;
        $metadata->table = ['table_name'];
        $metadata->customRepositoryClassName = 'CustomRepositoryClass';

        $metadata->fieldMappings['mappedField'] = [
            'columnName' => 'mapped_field',
            'fieldName' => 'mappedField'
        ];
        $metadata->fieldNames['mapped_field'] = 'mappedField';
        $metadata->columnNames['mappedField'] = 'mapped_field';

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
