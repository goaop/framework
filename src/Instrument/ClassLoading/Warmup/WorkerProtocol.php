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

/**
 * Line-based messages exchanged between the cache warmer and its worker processes
 *
 * Every message is one line: a marker followed by the serialized payload in base64, so file paths of any encoding
 * and cache records travel unchanged. A line without the marker is not a message but diagnostic output of the
 * worker, e.g. a fatal error of PHP.
 *
 * Coordinator → worker: `['file' => <path>]` for every file to weave; closing the input stops the worker.
 * Worker → coordinator: `['type' => 'ready']` once booted, `['type' => 'failed', 'error' => <message>]` when the
 * kernel could not be booted, `['type' => 'result', 'file' => <path>, 'error' => <message>|null, 'records' => [...]]`
 * for every file, the records being the cache metadata the file produced (CachePathManager::takeNewCacheState()).
 *
 * @internal
 */
final class WorkerProtocol
{
    /**
     * Marks the lines that carry a message
     */
    private const string MARKER = '@goaop-warmup:';

    /**
     * Encodes a message as one line, with its line feed
     *
     * @param array<string, mixed> $message
     */
    public static function encode(array $message): string
    {
        return self::MARKER . base64_encode(serialize($message)) . "\n";
    }

    /**
     * Decodes one line
     *
     * @return array<string, mixed>|null The message, or null when the line is not a message
     */
    public static function decode(string $line): ?array
    {
        $line = rtrim($line, "\r\n");
        if (!str_starts_with($line, self::MARKER)) {
            return null;
        }
        $payload = base64_decode(substr($line, strlen(self::MARKER)), true);
        if ($payload === false) {
            return null;
        }
        $message = unserialize($payload, ['allowed_classes' => false]);
        if (!is_array($message)) {
            return null;
        }

        /** @var array<string, mixed> $message Encoded by encode() */
        return $message;
    }
}
