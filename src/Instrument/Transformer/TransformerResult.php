<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

/**
 * Transformer result determines the status of applied transformation
 */
enum TransformerResult: string
{
    /**
     * Transformer decided to stop whole transformation process: the remaining transformers are skipped,
     * changes of the whole chain are reverted and the original source is served
     */
    case Aborted = 'aborted';

    /**
     * Transformer voted to abstain transformation, need to process following transformers to get result
     */
    case Abstain = 'abstain';

    /**
     * Source code was transformed, can process next transformers if needed
     */
    case Transformed = 'transformed';
}
