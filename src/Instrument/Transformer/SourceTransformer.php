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

namespace Go\Instrument\Transformer;

/**
 * General source transformer interface
 */
interface SourceTransformer
{

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param StreamMetaData $metadata Metadata for source
     * @return void|bool Return false if transformation should be stopped
     */
    public function transform(StreamMetaData $metadata);
}
