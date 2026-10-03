<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Bridge\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Proxy;
use RuntimeException;

/**
 * Validates the Doctrine configuration for woven entities.
 *
 * Woven entities need no metadata changes: the woven class keeps its name and all mapping
 * attributes, and the original class body lives in a trait that Doctrine never maps. An
 * intercepted property, however, is re-declared by the proxy with native PHP property hooks,
 * which Doctrine ORM only supports with native lazy objects. This listener reports such a
 * configuration when the metadata is loaded, instead of a failure on the first lazy proxy.
 *
 * Register it as a plain event listener when Doctrine is bootstrapped:
 *
 *     $eventManager->addEventListener(Events::loadClassMetadata, new MetadataLoadInterceptor());
 *
 * Use WovenEntityClassLocator for the mapping driver so that entity discovery loads the woven classes.
 */
final class MetadataLoadInterceptor
{
    /**
     * Handles \Doctrine\ORM\Events::loadClassMetadata event
     *
     * @throws RuntimeException When a mapped property of a woven entity is intercepted while native
     *                          lazy objects are disabled
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata        = $args->getClassMetadata();
        $reflectionClass = $metadata->getReflectionClass();
        if (!$reflectionClass->implementsInterface(Proxy::class)) {
            return;
        }
        if ($args->getObjectManager()->getConfiguration()->isNativeLazyObjectsEnabled()) {
            return;
        }

        $mappedProperties = [...array_keys($metadata->fieldMappings), ...array_keys($metadata->associationMappings)];
        foreach ($mappedProperties as $propertyName) {
            if ($reflectionClass->hasProperty($propertyName) && $reflectionClass->getProperty($propertyName)->hasHooks()) {
                throw new InvalidConfigurationException(sprintf(
                    'Mapped property %s::$%s of a woven entity is intercepted by an aspect, which requires '
                    . 'Doctrine native lazy objects: call $configuration->enableNativeLazyObjects(true).',
                    $metadata->getName(),
                    $propertyName,
                ));
            }
        }
    }
}
