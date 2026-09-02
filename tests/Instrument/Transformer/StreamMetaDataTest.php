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

namespace Go\Instrument\Transformer;

use PHPUnit\Framework\TestCase;

class StreamMetaDataTest extends TestCase
{
    public function testSourceIsRebuiltFromTokenStream(): void
    {
        $source = '<?php echo "hello world"; ?>';
        $stream = fopen('php://input', 'rb');
        assert($stream !== false);
        $metadata = new StreamMetaData($stream, $source);

        $this->assertSame($source, $metadata->source);

        // Mutating the token stream is reflected by subsequent reads
        foreach ($metadata->tokenStream as $token) {
            $token->text = str_replace('hello', 'brave new', $token->text);
        }
        $this->assertSame('<?php echo "brave new world"; ?>', $metadata->source);
    }

    public function testSettingSourceIsDeprecatedButRetokenizes(): void
    {
        $stream = fopen('php://input', 'rb');
        assert($stream !== false);
        $metadata = new StreamMetaData($stream, '<?php echo "old"; ?>');

        $deprecations = [];
        set_error_handler(function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, E_USER_DEPRECATED);
        try {
            $metadata->source = '<?php echo "new"; ?>';
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['Setting StreamMetaData->source is deprecated, use tokenStream instead'], $deprecations);
        $this->assertSame('<?php echo "new"; ?>', $metadata->source);
    }
}
