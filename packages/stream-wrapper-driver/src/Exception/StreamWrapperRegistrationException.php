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

namespace Go\Driver\StreamWrapper\Exception;

use Go\Aop\Exception\WeavingException;

/**
 * PHP refused to replace its `file://` stream wrapper with the weaving one
 */
final class StreamWrapperRegistrationException extends WeavingException
{
    public static function fileWrapperNotReplaced(string $wrapperClass): self
    {
        return new self(sprintf(
            'The stream wrapper weaving driver could not register %s as the "file://" stream wrapper '
            . '(stream_wrapper_register() failed); the native wrapper was restored.',
            $wrapperClass,
        ));
    }
}
