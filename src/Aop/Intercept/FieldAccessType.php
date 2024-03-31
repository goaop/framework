<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Intercept;

/**
 * This backed enum represents a field access type in the program.
 *
 * @api
 */
enum FieldAccessType: string
{
    /**
     * The read access type
     */
    case READ = 'get';

    /**
     * The write access type
     */
    case WRITE = 'set';
}
