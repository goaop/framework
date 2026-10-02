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

use InvalidArgumentException;

/**
 * Invalid kernel options, container registrations, console arguments or other user configuration
 */
class InvalidConfigurationException extends InvalidArgumentException implements ExceptionInterface {}
