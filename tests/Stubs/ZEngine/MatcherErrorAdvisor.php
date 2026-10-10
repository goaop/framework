<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

use Go\Aop\Advice;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Pointcut;
use Go\Aop\PointcutAdvisor;
use Go\ParserReflection\ReflectionFileNamespace;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/**
 * An advisor whose pointcut fails while matching the MatcherError* stubs (the way a pointcut reflecting an
 * ancestor it cannot resolve does) and matches nothing else
 */
final class MatcherErrorAdvisor implements PointcutAdvisor, Pointcut
{
    public const string FAILURE = 'The pointcut cannot decide on';

    public function getPointcut(): Pointcut
    {
        return $this;
    }

    public function getAdvice(): Advice
    {
        return new BeforeInterceptor(static function (): void {});
    }

    public function getKind(): int
    {
        return self::KIND_METHOD;
    }

    public function matches(
        ReflectionClass|ReflectionFileNamespace $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null,
    ): bool {
        if ($context instanceof ReflectionClass
            && in_array($context->getName(), [MatcherErrorTarget::class, MatcherErrorAspect::class], true)
        ) {
            throw new RuntimeException(self::FAILURE . ' ' . $context->getName());
        }

        return false;
    }

    public function compileToPhp(): Expr
    {
        return new New_(new FullyQualified(self::class));
    }
}
