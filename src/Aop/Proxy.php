<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop;

/**
 * Marker interface implemented by all AOP proxies.
 *
 * Used to detect whether or not objects are Go-generated proxies.
 *
 * @api
 */
interface Proxy
{
}
