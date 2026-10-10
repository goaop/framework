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

/**
 * Raised by the z-engine weaving driver when an advice matched a join point kind the driver
 * cannot weave at runtime (property access, introductions, initialization join points,
 * function interception)
 *
 * The exception escapes the autoloader at the first load of the offending class: the class
 * is then declared and linked, but NOT woven. Narrow the pointcut, or use the stream driver
 * for such aspects.
 */
final class UnsupportedJoinpointException extends WeavingException
{
    /**
     * @param list<string> $kinds      Join-point kinds matched for the class (AspectContainer prefixes)
     * @param list<string> $advisorIds Advisor identifiers behind those join points
     */
    public static function forClass(string $className, array $kinds, array $advisorIds): self
    {
        return new self(sprintf(
            'The zengine weaving driver cannot weave %s: the advisor(s) %s match join point kind(s) %s, '
            . 'which only the stream driver supports (runtime weaving rewires method bodies only). '
            . 'Narrow the pointcut(s) to method execution, or run this application with the stream driver.',
            $className,
            implode(', ', $advisorIds),
            implode(', ', $kinds),
        ));
    }

    /**
     * The class cannot be rewired because z-engine refused the engine mutation
     */
    public static function engineRefusal(string $className, \Throwable $reason): self
    {
        return new self(sprintf(
            'The zengine weaving driver cannot weave %s: %s',
            $className,
            $reason->getMessage(),
        ), 0, $reason);
    }
}
