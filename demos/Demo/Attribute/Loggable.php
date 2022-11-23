<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Attribute;

use Attribute;
use Go\Lang\Attribute\BaseAttribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Loggable extends BaseAttribute
{
}
