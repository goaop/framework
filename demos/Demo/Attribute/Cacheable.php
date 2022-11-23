<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Attribute;

use Attribute;
use Go\Lang\Attribute\BaseAttribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Cacheable extends BaseAttribute
{
    /**
     * Time to cache
     */
    public int $time = 0;

    public function __construct(int $time)
    {
        $this->time = $time;
        parent::__construct();
    }
}
