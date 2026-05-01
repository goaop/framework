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

namespace Go\Stubs;

/**
 * Original method bodies, converted to a trait exactly as WeavingTransformer would do.
 * Kept in its own file so PSR-4 autoloading resolves Go\Stubs\TraitAliasProxied directly.
 */
trait TraitAliasProxied
{
    public int $public = T_PUBLIC;

    public function publicMethod(): int
    {
        return $this->public;
    }

    public function getObjectId(): int
    {
        return spl_object_id($this);
    }

    protected function protectedMethod(): int
    {
        return T_PROTECTED;
    }

    private function privateMethod(): int
    {
        return T_PRIVATE;
    }

    public function variadicArgsTest(mixed ...$args): string
    {
        return implode('', $args);
    }

    public function passByReference(mixed &$ref): mixed
    {
        $ref = null;

        return null;
    }

    public static function staticPassByReference(mixed &$ref): mixed
    {
        $ref = null;

        return null;
    }

    public static function staticPublicMethod(): string
    {
        return static::class;
    }

    public static function staticVariadicArgsTest(mixed ...$args): string
    {
        return implode('', $args);
    }
}
