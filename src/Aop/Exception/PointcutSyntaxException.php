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

use Dissect\Parser\Exception\UnexpectedTokenException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

/**
 * A pointcut expression that can not be lexed or parsed, or a pattern inside it that is invalid
 */
final class PointcutSyntaxException extends AspectException
{
    public static function invalidExpression(string $expression, Throwable $previous): self
    {
        return new self("Invalid pointcut expression `{$expression}`: {$previous->getMessage()}", 0, $previous);
    }

    /**
     * @param ReflectionMethod|ReflectionProperty|ReflectionClass<*> $definedIn
     */
    public static function unrecognizedLexicalStructure(
        string $expression,
        ReflectionMethod|ReflectionProperty|ReflectionClass $definedIn,
        Throwable $previous,
    ): self {
        return new self(sprintf(
            'Can not recognize the lexical structure `%s` before %s, defined in %s:%d',
            $expression,
            ...self::describeDefinition($definedIn),
        ), 0, $previous);
    }

    /**
     * @param ReflectionMethod|ReflectionProperty|ReflectionClass<*> $definedIn
     */
    public static function unexpectedToken(
        string $expression,
        ReflectionMethod|ReflectionProperty|ReflectionClass $definedIn,
        UnexpectedTokenException $previous,
    ): self {
        [$member, $fileName, $line] = self::describeDefinition($definedIn);

        return new self(sprintf(
            'Unexpected token %s in the `%s` before %s, defined in %s:%d.' . PHP_EOL . 'Expected one of: %s',
            $previous->getToken()->getValue(),
            $expression,
            $member,
            $fileName,
            $line,
            implode(', ', $previous->getExpected()),
        ), 0, $previous);
    }

    public static function unexpectedParseResult(): self
    {
        return new self('Expected instance of Pointcut to be received during parsing');
    }

    public static function tokenValueNotString(): self
    {
        return new self('Token value must be a string');
    }

    public static function multipleModifierAlternatives(): self
    {
        return new self('Only one group of modifier alternatives (a|b) is supported per member pattern');
    }

    public static function emptyReturnType(): self
    {
        return new self('Return type name must not be empty');
    }

    public static function namePatternNotMatchable(string $pattern, string $error): self
    {
        return new self("Name pattern `{$pattern}` can not be matched: {$error}");
    }

    public static function returnTypePatternNotMatchable(string $pattern, string $error): self
    {
        return new self("Return type pattern `{$pattern}` can not be matched: {$error}");
    }

    /**
     * Returns the member, the file and the line where a pointcut expression is defined
     *
     * @param ReflectionMethod|ReflectionProperty|ReflectionClass<*> $definedIn
     *
     * @return array{string, string, int}
     */
    private static function describeDefinition(ReflectionMethod|ReflectionProperty|ReflectionClass $definedIn): array
    {
        if ($definedIn instanceof ReflectionProperty) {
            return [$definedIn->class . '->' . $definedIn->name, (string) $definedIn->getDeclaringClass()->getFileName(), 0];
        }
        $member = $definedIn instanceof ReflectionMethod ? $definedIn->class . '->' . $definedIn->name : $definedIn->name;

        return [$member, (string) $definedIn->getFileName(), (int) $definedIn->getStartLine()];
    }
}
