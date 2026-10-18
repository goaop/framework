<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Exception;

use Go\Aop\Advice;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/**
 * Invalid aspect definition: advice methods, attributes, pointcut references or advisors
 *
 * Catch {@see ExceptionInterface} to handle every exception thrown by the framework.
 */
class AspectException extends RuntimeException implements ExceptionInterface
{
    public static function multipleAdviceAttributes(ReflectionMethod $adviceMethod, int $attributeCount): self
    {
        return new self(sprintf(
            'Advice method %s::%s() declares %d advice attributes, only one is supported per method (%s:%d)',
            $adviceMethod->class,
            $adviceMethod->name,
            $attributeCount,
            $adviceMethod->getFileName(),
            $adviceMethod->getStartLine(),
        ));
    }

    public static function nonPublicAdviceMethod(ReflectionMethod $adviceMethod): self
    {
        return new self(
            "Advice method {$adviceMethod->class}::{$adviceMethod->name}() must be public; "
            . 'first-class advice callables require all advice methods to be public',
        );
    }

    public static function unsupportedAdviceAttribute(object $attribute): self
    {
        return new self('Unsupported method meta class: ' . $attribute::class);
    }

    public static function unsupportedPropertyAttribute(string $attributeName, ReflectionProperty $aspectProperty): self
    {
        return new self(sprintf(
            'Attribute %s is not supported on aspect property %s::$%s, only #[DeclareParents] is',
            $attributeName,
            $aspectProperty->class,
            $aspectProperty->name,
        ));
    }

    public static function unsupportedIntroductionAttribute(object $attribute): self
    {
        return new self('Unsupported attribute class: ' . $attribute::class);
    }

    public static function pointcutReferenceNotPointcut(string $pointcutId, mixed $value): self
    {
        return new self("Pointcut reference {$pointcutId} resolves to " . get_debug_type($value) . ', not to a pointcut');
    }

    public static function adviceMethodNameRequired(): self
    {
        return new self('Advice method name is required when an aspect class name is given');
    }

    public static function advisorWithoutAdvice(string $advisorId, mixed $advice): self
    {
        return new self("Advisor {$advisorId} provides " . get_debug_type($advice) . ' instead of advice instance');
    }

    public static function unsupportedAdvice(string $advisorId, Advice $advice): self
    {
        return new self(
            "Advisor {$advisorId} uses unsupported advice " . get_debug_type($advice)
            . '; only framework aspect-method interceptors can be generated',
        );
    }
}
