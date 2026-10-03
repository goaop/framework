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

/**
 * Failure while transforming a source file, generating a proxy or writing the cache
 */
class WeavingException extends RuntimeException implements ExceptionInterface {}
