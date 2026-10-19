<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Exception;

use RuntimeException;
use Throwable;

/**
 * Failure while transforming a source file, generating a proxy or writing the cache
 */
class WeavingException extends RuntimeException implements ExceptionInterface
{
    public static function streamFilterAlreadyRegistered(string $filterId): self
    {
        return new self("Stream filter {$filterId} is already registered");
    }

    public static function streamFilterRegistrationFailed(string $filterId): self
    {
        return new self("Stream filter {$filterId} could not be registered, the name is taken by another filter");
    }

    public static function streamFilterNotRegistered(): self
    {
        return new self('Stream filter is not registered yet, call SourceTransformingLoader::register() first');
    }

    public static function invalidStream(mixed $stream): self
    {
        return new self('Stream must be a valid resource, got ' . get_debug_type($stream));
    }

    public static function streamWithoutUri(): self
    {
        return new self('Stream has no uri, only file streams can be transformed');
    }

    public static function transformerFailed(string $transformerClass, string $uri, Throwable $previous): self
    {
        return new self("{$transformerClass} failed to transform {$uri}: {$previous->getMessage()}", previous: $previous);
    }

    public static function prebuiltCacheVersionMismatch(string $cacheDir): self
    {
        return new self(sprintf(
            'The AOP cache in %s was built by another version of the framework, '
            . 'rebuild it with `bin/aspect cache:warmup:aop` before using Features::PREBUILT_CACHE',
            $cacheDir,
        ));
    }

    public static function cacheDirectoryNotCreated(string $directory): self
    {
        return new self(sprintf('Unable to create cache directory "%s".', $directory));
    }

    public static function cacheFileNotWritten(string $fileName): self
    {
        return new self(sprintf('Unable to write cache file "%s".', $fileName));
    }

    public static function declarationWithoutTokenPositions(string $className): self
    {
        return new self("Declaration of {$className} has no token positions to weave");
    }

    public static function constructorOfMissingClass(string $className): self
    {
        return new self("Cannot instantiate non-existent class: {$className}");
    }

    public static function invalidParseTable(): self
    {
        return new self('Pointcut parse table PointcutParseTable.php must return an array');
    }

    public static function invalidInterceptorDescriptor(mixed $interceptor): self
    {
        return new self('Interceptor list expects generated interceptor descriptors, got ' . get_debug_type($interceptor));
    }

    public static function incompleteInterceptorDescriptor(): self
    {
        return new self('Aspect-backed interceptor descriptor is incomplete');
    }

    public static function unsupportedValueType(mixed $value): self
    {
        return new self('Cannot generate AST node for value of type: ' . get_debug_type($value));
    }

    public static function unsupportedTypeNode(string $nodeType): self
    {
        return new self('Unsupported type node ' . $nodeType);
    }

    public static function malformedDnfType(string $type): self
    {
        return new self("Malformed DNF type: {$type}");
    }

    public static function parameterClosureDefault(string $parameterName): self
    {
        return new self(sprintf(
            'Cannot generate proxy for parameter $%s: PHP 8.5 Closure default values '
            . 'require goaop/parser-reflection for AST access.',
            $parameterName,
        ));
    }

    public static function propertyClosureDefault(string $className, string $propertyName): self
    {
        return new self(sprintf(
            'Cannot generate proxy for property %s::$%s: PHP 8.5 Closure default values '
            . 'require goaop/parser-reflection for AST access.',
            $className,
            $propertyName,
        ));
    }

    public static function propertyNotHookable(string $className, string $propertyName): self
    {
        return new self(sprintf('Property %s::$%s cannot be intercepted with native hooks', $className, $propertyName));
    }

    public static function methodReturnsByReference(string $className, string $methodName): self
    {
        return new self(sprintf(
            'Method %s::%s() returns by reference and can not be intercepted: the joinpoint returns '
            . 'values, so the reference would be lost. Exclude it from the pointcut with '
            . '"&& !matchReturningByReference()".',
            $className,
            $methodName,
        ));
    }

    public static function traitImportedOverrideNotInterceptable(string $className, string $methodName, string $traitName): self
    {
        return new self(sprintf(
            'Method %s::%s() is imported from trait %s and has #[\Override], so it can not be intercepted: PHP copies '
            . 'the attribute to the private "%sOriginalAlias" alias of the proxy, which overrides nothing. Exclude it '
            . 'from the pointcut with "&& !matchInherited()".',
            $className,
            $methodName,
            $traitName,
            $methodName,
        ));
    }

    public static function functionReturnsByReference(string $functionName): self
    {
        return new self(sprintf(
            'Function %s() returns by reference and can not be intercepted: the joinpoint returns values, '
            . 'so the reference would be lost. Exclude it from the pointcut with "&& !matchReturningByReference()".',
            $functionName,
        ));
    }
}
