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

use OutOfBoundsException;

/**
 * A service or value that is not registered in the aspect container
 */
class ServiceNotFoundException extends OutOfBoundsException implements ExceptionInterface {}
