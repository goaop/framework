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
        $pointcutExpression = $interceptorAttribute->expression;
        switch (true) {
            case ($interceptorAttribute instanceof DeclareError):
                $errorMessage = $aspectProperty->getDefaultValue();
                return new DeclareErrorInterceptor($errorMessage, $interceptorAttribute->level, $pointcutExpression);

            case ($interceptorAttribute instanceof DeclareParents):
                return new TraitIntroductionInfo($interceptorAttribute->trait, $interceptorAttribute->interface);

            default:
                throw new UnexpectedValueException('Unsupported attribute class: ' . get_class($interceptorAttribute));
        }
    }
}
