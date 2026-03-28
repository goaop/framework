<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Example;

use Demo\Attribute\Loggable;

/**
 * Example class to show how to use logging with AOP
 */
enum LoggingEnum: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';

    /**
     * Protected method can be also loggable
     */
    #[Loggable]
    public static function names(): array
    {
        return array_column(self::cases(), 'name');

    }

    #[Loggable]
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    #[Loggable]
    public static function asArray(): array
    {
        if (empty(self::values())) {
            return self::names();
        }

        if (empty(self::names())) {
            return self::values();
        }

        return array_column(self::cases(), 'value', 'name');
    }
}
