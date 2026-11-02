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

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * An advice of an aspect is about to run while the aspect is being created.
 *
 * A deferred aspect is created when one of its advices runs first, after its dependencies. When creating a
 * dependency (directly or through other services) runs an advice of the same aspect, for example because the
 * aspect advises the constructor of a service it depends on, that advice would run on an aspect that does
 * not exist yet.
 */
final class AspectCircularReferenceException extends RuntimeException implements ExceptionInterface, ContainerExceptionInterface
{
    /**
     * Class of the aspect being created
     */
    public private(set) string $aspectClass = '';

    /**
     * @var list<string> Calls from the creation of the aspect to its advice, outermost first
     */
    public private(set) array $path = [];

    /**
     * @param list<string> $path Calls from the creation of the aspect to its advice, outermost first, the
     *                           advice included
     */
    public static function adviceRunsDuringCreation(string $aspectClass, string $joinpoint, array $path): self
    {
        $exception = new self(sprintf(
            'Circular reference detected for the aspect "%s", path: "%s". The aspect is created when one of its '
            . 'advices runs first, and creating its dependencies ran its advice at %s, which can not run before '
            . 'the aspect exists. Inject the dependency lazily (a lazy service, a closure or a service locator), '
            . 'or narrow the pointcut so that the aspect does not advise the creation of its own dependencies.',
            $aspectClass,
            implode(' -> ', [$aspectClass, ...$path]),
            $joinpoint,
        ));
        $exception->aspectClass = $aspectClass;
        $exception->path        = $path;

        return $exception;
    }
}
