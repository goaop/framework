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

namespace Go\Instrument\ClassLoading\Warmup;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkerProtocolTest extends TestCase
{
    public function testMessageSurvivesAsOneLine(): void
    {
        // A path with a line feed and bytes that are not UTF-8 still travel unchanged
        $message = ['type' => 'result', 'file' => "/app/odd\nname\xff.php", 'error' => null, 'records' => ['/a.php' => ['filemtime' => 1]]];

        $line = WorkerProtocol::encode($message);

        $this->assertStringEndsWith("\n", $line);
        $this->assertSame(1, substr_count($line, "\n"));
        $this->assertSame($message, WorkerProtocol::decode($line));
        $this->assertSame($message, WorkerProtocol::decode(rtrim($line) . "\r\n"));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function linesThatAreNoMessage(): iterable
    {
        yield 'stray output'      => ["Deprecated: something\n"];
        yield 'empty line'        => [''];
        yield 'broken base64'     => ["@goaop-warmup:%%%\n"];
        yield 'not serialized'    => ['@goaop-warmup:' . base64_encode('garbage') . "\n"];
        yield 'not an array'      => ['@goaop-warmup:' . base64_encode(serialize('text')) . "\n"];
        yield 'object is refused' => ['@goaop-warmup:' . base64_encode(serialize(new \ArrayObject())) . "\n"];
    }

    #[DataProvider('linesThatAreNoMessage')]
    public function testLineThatIsNoMessageDecodesToNull(string $line): void
    {
        $this->assertNull(@WorkerProtocol::decode($line));
    }
}
