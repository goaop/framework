<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Container;

use OutOfBoundsException;
use Psr\Container\NotFoundExceptionInterface;

final class ApplicationServiceNotFoundException extends OutOfBoundsException implements NotFoundExceptionInterface {}
