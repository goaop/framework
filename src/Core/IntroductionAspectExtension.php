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

namespace Go\Core;

use Go\Aop\Advice;
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use Go\Aop\Framework\TraitIntroductionInfo;
use Go\Aop\Pointcut;
use Go\Aop\Support\GenericPointcutAdvisor;
use Go\Lang\Attribute\AbstractAttribute;
use Go\Lang\Attribute\DeclareParents;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

/**
 * Introduction aspect extension
 */
class IntroductionAspectExtension extends AbstractAspectLoaderExtension
{
    public function load(Aspect $aspect, ReflectionClass $reflectionAspect): array
    {
        $loadedItems = [];
        foreach ($reflectionAspect->getProperties() as $aspectProperty) {
            $propertyId = $reflectionAspect->getName() . '->' . $aspectProperty->getName();
            // Only the framework's own attributes are interpreted, others are ignored
            $attributes = $aspectProperty->getAttributes(AbstractAttribute::class, ReflectionAttribute::IS_INSTANCEOF);

            foreach ($attributes as $reflectionAttribute) {
                // Checked before instantiating: PHP itself rejects an advice attribute on a property
                $attribute = is_a($reflectionAttribute->getName(), DeclareParents::class, true)
                    ? $reflectionAttribute->newInstance()
                    : null;
                if ($attribute instanceof DeclareParents) {
                    $pointcut = $this->parsePointcut($aspect, $aspectProperty, $attribute->expression);
                    // Introduction doesn't have own syntax and uses any suitable class-filter
                    $pointcut = new Pointcut\AndPointcut(
                        Pointcut::KIND_INTRODUCTION | Pointcut::KIND_CLASS,
                        $pointcut,
                    );
                    $advice  = $this->getAdvice($attribute, $aspect, $aspectProperty);
                    $advisor = new GenericPointcutAdvisor($pointcut, $advice);

                    $loadedItems[$propertyId] = $advisor;
                } else {
                    throw new AspectException(sprintf(
                        'Attribute %s is not supported on aspect property %s::$%s, only #[DeclareParents] is',
                        $reflectionAttribute->getName(),
                        $aspectProperty->class,
                        $aspectProperty->name,
                    ));
                }
            }
        }

        return $loadedItems;
    }

    /**
     * Returns an interceptor instance by meta-type attribute and closure
     *
     * @throws AspectException For unsupported attributes
     */
    protected function getAdvice(
        AbstractAttribute $interceptorAttribute,
        Aspect $aspect,
        ReflectionProperty $aspectProperty,
    ): Advice {
        return match (true) {
            $interceptorAttribute instanceof DeclareParents
                => new TraitIntroductionInfo($interceptorAttribute->traitName, $interceptorAttribute->interfaceName),
            default
            => throw new AspectException('Unsupported attribute class: ' . $interceptorAttribute::class),
        };
    }
}
