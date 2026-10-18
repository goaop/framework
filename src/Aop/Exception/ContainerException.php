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

/**
 * A container entry that is registered, but can not be retrieved as declared
 */
final class ContainerException extends InvalidConfigurationException implements ContainerExceptionInterface
{
    public static function serviceNotProperlyRegistered(string $id): self
    {
        return new self("Service {$id} is not properly registered");
    }
}
