<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Advice;
use Go\Aop\Aspect;
use Go\Aop\Framework\DeclareErrorInterceptor;
use Go\Aop\Framework\TraitIntroductionInfo;
use Go\Aop\Pointcut;
use Go\Aop\Support\GenericPointcutAdvisor;
use Go\Lang\Attribute\AbstractAttribute;
use Go\Lang\Attribute\DeclareError;
use Go\Lang\Attribute\DeclareParents;
use ReflectionClass;
use ReflectionProperty;
use UnexpectedValueException;

/**
 * Introduction aspect extension
 */
class IntroductionAspectExtension extends AbstractAspectLoaderExtension
{

    public function load(Aspect $aspect, ReflectionClass $reflectionAspect): array
    {
        $loadedItems = [];
        foreach ($reflectionAspect->getProperties() as $aspectProperty) {
            $propertyId = $reflectionAspect->getName() . '->'. $aspectProperty->getName();
            $attributes = $aspectProperty->getAttributes();

            foreach ($attributes as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();
                if ($attribute instanceof DeclareParents) {
                    $pointcut = $this->parsePointcut($aspect, $aspectProperty, $attribute->expression);
                    // Introduction doesn't have own syntax and uses any suitable class-filter
                    $pointcut = new Pointcut\AndPointcut(
                        Pointcut::KIND_INTRODUCTION | Pointcut::KIND_CLASS,
                        $pointcut
                    );
                    $advice  = $this->getAdvice($attribute, $aspect, $aspectProperty);
                    $advisor = new GenericPointcutAdvisor($pointcut, $advice);

                    $loadedItems[$propertyId] = $advisor;
                } elseif ($attribute instanceof DeclareError) {
                    $pointcut = $this->parsePointcut($aspect, $reflectionAspect, $attribute->expression);
                    $advice   = $this->getAdvice($attribute, $aspect, $aspectProperty);

                    $loadedItems[$propertyId] = new GenericPointcutAdvisor($pointcut, $advice);
                } else {
                    throw new UnexpectedValueException('Unsupported attribute class: ' . get_class($attribute));
                }
            }
        }

        return $loadedItems;
    }

    /**
     * Returns an interceptor instance by meta-type attribute and closure
     *
     * @throws UnexpectedValueException For unsupported annotations
     */
    protected function getAdvice(
        AbstractAttribute $interceptorAttribute,
        Aspect $aspect,
        ReflectionProperty $aspectProperty
    ): Advice {
        return match (true) {
            $interceptorAttribute instanceof DeclareError =>
                $this->createDeclareErrorAdvice($aspectProperty, $interceptorAttribute),
            $interceptorAttribute instanceof DeclareParents =>
                new TraitIntroductionInfo($interceptorAttribute->trait, $interceptorAttribute->interface),
            default =>
                throw new UnexpectedValueException('Unsupported attribute class: ' . get_class($interceptorAttribute)),
        };
    }

    /**
     * Creates a DeclareErrorInterceptor after validating the property's default value.
     *
     * @throws \UnexpectedValueException if the property default value is not a non-empty string
     */
    private function createDeclareErrorAdvice(ReflectionProperty $aspectProperty, DeclareError $attribute): DeclareErrorInterceptor
    {
        $errorMessage = $aspectProperty->getDefaultValue();
        if (!is_string($errorMessage) || $errorMessage === '') {
            throw new \UnexpectedValueException('DeclareError property must have a non-empty string default value');
        }

        return new DeclareErrorInterceptor($errorMessage, $attribute->level, $attribute->expression);
    }
}
