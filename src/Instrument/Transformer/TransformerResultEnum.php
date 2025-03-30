<?php

declare(strict_types = 1);
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
enum TransformerResultEnum: string
{
    /**
     * Transformer decided to stop whole transformation process, all changes should be reverted
     */
    case RESULT_ABORTED = 'aborted';

    /**
     * Transformer voted to abstain transformation, need to process following transformers to get result
     */
    case RESULT_ABSTAIN = 'abstain';

    /**
     * Source code was transformed, can process next transformers if needed
     */
    case RESULT_TRANSFORMED = 'transformed';
}
