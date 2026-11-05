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

use RuntimeException;

/**
 * Target whose members take a #[\SensitiveParameter] argument and throw, for stack trace checks (#795)
 */
final class SensitiveArgumentsTarget
{
    public function __construct(string $user = '', #[\SensitiveParameter] string $password = '')
    {
        if ($password !== '') {
            throw new RuntimeException('Constructor of ' . $user);
        }
    }

    public function login(string $user, #[\SensitiveParameter] string $password): never
    {
        throw new RuntimeException('Login of ' . $user);
    }

    public static function staticLogin(string $user, #[\SensitiveParameter] string ...$passwords): never
    {
        throw new RuntimeException('Static login of ' . $user);
    }
}
