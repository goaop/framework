<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\Bridge\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Go\Core\AspectContainer;

/**
 * Class MetadataLoadInterceptor
 *
 * Support for weaving Doctrine entities.
 */
final class MetadataLoadInterceptor implements EventSubscriber
{
    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [
            Events::loadClassMetadata
        ];
    }

    /**
     * Handles \Doctrine\ORM\Events::loadClassMetadata event by modifying metadata of Go! AOP proxied classes.
     *
     * This method intercepts loaded metadata of Doctrine's entities which are weaved by Go! AOP,
     * and denotes them as mapped superclass. If weaved entities uses mappings from traits
     * (such as Timestampable, Blameable, etc... from https://github.com/Atlantic18/DoctrineExtensions),
     * it will remove all mappings from proxied class for fields inherited from traits in order to prevent
     * collision with concrete subclass of weaved entity. Fields from trait will be present in concrete subclass
     * of weaved entitites.
     *
     * @see http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/inheritance-mapping.html#mapped-superclasses
     * @see https://github.com/Atlantic18/DoctrineExtensions
     *
     * @param LoadClassMetadataEventArgs $args
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $args)
    {
        /**
         * @var ClassMetadata $metadata
         */
        $metadata = $args->getClassMetadata();

        if (1 === preg_match(sprintf('/.+(%s)$/', AspectContainer::AOP_PROXIED_SUFFIX), $metadata->name)) {
            $metadata->isMappedSuperclass           = true;
            $metadata->isEmbeddedClass              = false;
            $metadata->table                        = [];
            $metadata->customRepositoryClassName    = null;

            $this->removeMappingsFromTraits($metadata);
        }
    }

    /**
     * Remove fields in Go! AOP proxied class metadata that are inherited
     * from traits.
     *
     * @param ClassMetadata $metadata
     */
    private function removeMappingsFromTraits(ClassMetadata $metadata)
    {
        $traits = $this->getTraits($metadata->name);

        foreach ($traits as $trait) {
            $trait = new \ReflectionClass($trait);

            /**
             * @var \ReflectionProperty $property
             */
            foreach ($trait->getProperties() as $property) {
                $name = $property->getName();

                if (isset($metadata->fieldMappings[$name])) {
                    $mapping = $metadata->fieldMappings[$name];

                    unset(
                        $metadata->fieldMappings[$name],
                        $metadata->fieldNames[$mapping['columnName']],
                        $metadata->columnNames[$name]
                    );
                }
            }
        }
    }

    /**
     * Get ALL traits used by one class.
     *
     * This method is copied from https://github.com/RunOpenCode/traitor-bundle/blob/master/src/RunOpenCode/Bundle/Traitor/Utils/ClassUtils.php
     *
     * @param object|string $objectOrClass Instance of class or FQCN
     * @param bool $autoload Weather to autoload class.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return array Used traits.
     */
    private function getTraits($objectOrClass, $autoload = true)
    {
        if (is_object($objectOrClass)) {
            $objectOrClass = get_class($objectOrClass);
        }

        if (!is_string($objectOrClass)) {
            throw new \InvalidArgumentException(sprintf('Full qualified class name expected, got: "%s".', gettype($objectOrClass)));
        }

        if (!class_exists($objectOrClass)) {
            throw new \RuntimeException(sprintf('Class "%s" does not exists or it can not be autoloaded.', $objectOrClass));
        }

        $traits = [];
        // Get traits of all parent classes
        do {
            $traits = array_merge(class_uses($objectOrClass, $autoload), $traits);
        } while ($objectOrClass = get_parent_class($objectOrClass));

        $traitsToSearch = $traits;

        while (count($traitsToSearch) > 0) {
            $newTraits = class_uses(array_pop($traitsToSearch), $autoload);
            $traits = array_merge($newTraits, $traits);
            $traitsToSearch = array_merge($newTraits, $traitsToSearch);
        }

        foreach ($traits as $trait => $same) {
            $traits = array_merge(class_uses($trait, $autoload), $traits);
        }

        return array_unique(array_map(function ($fqcn) {
            return ltrim($fqcn, '\\');
        }, $traits));
    }
}
