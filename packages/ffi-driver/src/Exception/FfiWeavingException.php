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

namespace Go\Driver\Ffi\Exception;

use Go\Aop\Exception\WeavingException;

/**
 * Weave-time failures of the ffi driver: the donor of a class cannot be generated, written or loaded
 */
class FfiWeavingException extends WeavingException
{
    /**
     * @param list<string> $kinds Join-point kinds (AspectContainer prefixes) the advices contain
     */
    public static function unsupportedDonorKinds(string $className, array $kinds): self
    {
        return new self(sprintf(
            'A donor trait carries method dispatchers only, the advices of %s contain the join-point kind(s) %s',
            $className,
            implode(', ', $kinds),
        ));
    }

    public static function sourceOutsideApplicationRoot(string $resource, string $appDir): self
    {
        return new self(sprintf('The source %s lies outside the application root %s and is never woven', $resource, $appDir));
    }

    public static function donorNotDeclared(string $donorFile, string $className, string $donorTrait): self
    {
        return new self(sprintf(
            'The donor file %s of %s does not declare the donor trait %s: clear the cache directory',
            $donorFile,
            $className,
            $donorTrait,
        ));
    }
}
