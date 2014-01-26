<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use Go\Instrument\Transformer\SourceTransformer;

/**
 * @package go
 * @subpackage instrument
 */
interface LoadTimeWeaver
{

    /**
     * Adds a SourceTransformer to be applied by this LoadTimeWeaver.
     *
     * @param $transformer SourceTransformer Transformer for source code
     *
     * @return void
     */
    public function addTransformer(SourceTransformer $transformer);
}
