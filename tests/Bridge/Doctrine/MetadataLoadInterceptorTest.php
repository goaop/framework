<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Bridge\Doctrine;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Go\Aop\Proxy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MetadataLoadInterceptorTest extends TestCase
{
    public function testRejectsInterceptedMappedPropertyWithoutNativeLazyObjects(): void
    {
        $event = $this->createEvent(WovenHookedEntityStub::class, nativeLazyObjects: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WovenHookedEntityStub::$name of a woven entity is intercepted');

        new MetadataLoadInterceptor()->loadClassMetadata($event);
    }

    public function testAcceptsInterceptedMappedPropertyWithNativeLazyObjects(): void
    {
        $event = $this->createEvent(WovenHookedEntityStub::class, nativeLazyObjects: true);

        new MetadataLoadInterceptor()->loadClassMetadata($event);

        $this->assertArrayHasKey('name', $event->getClassMetadata()->fieldMappings);
    }

    public function testIgnoresEntitiesThatAreNotWoven(): void
    {
        $event = $this->createEvent(PlainHookedEntityStub::class, nativeLazyObjects: false);

        new MetadataLoadInterceptor()->loadClassMetadata($event);

        $this->assertFalse($event->getClassMetadata()->isMappedSuperclass);
    }

    /**
     * @param class-string $className
     */
    private function createEvent(string $className, bool $nativeLazyObjects): LoadClassMetadataEventArgs
    {
        $metadata = new ClassMetadata($className);
        $metadata->initializeReflection(new RuntimeReflectionService());
        $metadata->mapField(['fieldName' => 'name', 'type' => 'string']);

        $configuration = new Configuration();
        $configuration->enableNativeLazyObjects($nativeLazyObjects);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConfiguration')->willReturn($configuration);

        return new LoadClassMetadataEventArgs($metadata, $entityManager);
    }
}

/**
 * Shape of a woven entity whose mapped property is intercepted: a proxy with a hooked property
 */
final class WovenHookedEntityStub implements Proxy
{
    public string $name = '' {
        get => $this->name;
        set => $this->name = $value;
    }
}

final class PlainHookedEntityStub
{
    public string $name = '' {
        get => $this->name;
        set => $this->name = $value;
    }
}
