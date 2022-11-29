<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Framework\DeclareErrorInterceptor;
use Go\Aop\Framework\TraitIntroductionInfo;
use Go\Aop\Pointcut;
use Go\Aop\Support\DeclareParentsAdvisor;
use Go\Aop\Support\DefaultPointcutAdvisor;
use Go\Lang\Attribute;
use Go\Lang\Attribute\DeclareParents;
use ReflectionAttribute;
use ReflectionClass;
use UnexpectedValueException;

/**
 * Introduction aspect extension
 */
class IntroductionAspectExtension extends AbstractAspectLoaderExtension
{
    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param Aspect          $aspect           Instance of aspect
     * @param ReflectionClass $reflectionAspect Reflection of point
     *
     * @return array<string,Pointcut>|array<string,Advisor>
     *
     * @throws UnexpectedValueException
     */
    public function load(Aspect $aspect, ReflectionClass $reflectionAspect): array
    {
        $loadedItems = [];
        foreach ($reflectionAspect->getProperties() as $aspectProperty) {
            $propertyId  = $reflectionAspect->getName() . '->'. $aspectProperty->getName();
            $attributes  = $aspectProperty->getAttributes(
                Attribute\BaseAttribute::class,
                ReflectionAttribute::IS_INSTANCEOF
            );

            foreach ($attributes as $attribute) {
                $attributeInstance = $attribute->newInstance();
                if ($attributeInstance instanceof DeclareParents) {
                    $pointcut = $this->parsePointcut($aspect, $aspectProperty, $attributeInstance->value);

                    $implement        = $attributeInstance->defaultImpl;
                    $interface        = $attributeInstance->interface;
                    $introductionInfo = new TraitIntroductionInfo($implement, $interface);
                    $advisor          = new DeclareParentsAdvisor($pointcut, $introductionInfo);

                    $loadedItems[$propertyId] = $advisor;
                } elseif ($attributeInstance instanceof Attribute\DeclareError) {
                    $pointcut = $this->parsePointcut($aspect, $reflectionAspect, $attributeInstance->value);

                    $aspectProperty->setAccessible(true);
                    $errorMessage     = $aspectProperty->getValue($aspect);
                    $errorLevel       = $attributeInstance->level;
                    $introductionInfo = new DeclareErrorInterceptor(
                        $errorMessage,
                        $errorLevel,
                        $attributeInstance->value
                    );
                    $loadedItems[$propertyId] = new DefaultPointcutAdvisor($pointcut, $introductionInfo);
                    break;

                } else {
                    throw new UnexpectedValueException(
                        'Unsupported attribute class: ' . get_class($attribute)
                    );
                }
            }
        }

        return $loadedItems;
    }
}
