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

use Go\Aop\Exception\WeavingException;
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

    public function testSourceIsReadOnly(): void
    {
        $stream = fopen('php://input', 'rb');
        assert($stream !== false);
        $metadata = new StreamMetaData($stream, '<?php echo "old"; ?>');

        // The wording differs between PHP versions ("is read-only" / "get-only virtual property")
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('StreamMetaData::$source');

        // @phpstan-ignore assign.propertyReadOnly (writing is exactly what is under test)
        $metadata->source = '<?php echo "new"; ?>';
    }

    public function testRejectsStreamWithoutUri(): void
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->assertIsArray($sockets);

        $this->expectException(WeavingException::class);
        $this->expectExceptionMessage('Stream has no uri');

        new StreamMetaData($sockets[0], '<?php');
    }

    public function testRejectsNonResourceStream(): void
    {
        $this->expectException(WeavingException::class);
        $this->expectExceptionMessage('Stream should be valid resource');

        // @phpstan-ignore argument.type (the guard is exactly what is under test)
        new StreamMetaData('php://memory');
    }
}
