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

namespace Demo\Showcase;

use Closure;
use Go\Aop\Proxy;
use Go\Instrument\Transformer\MagicConstantTransformer;
use ReflectionClass;
use ReflectionFunction;

/**
 * Piece of PHP source code shown on the page
 */
final readonly class SourceCode
{
    /**
     * Root directory of the demo, file names are shown relative to it
     */
    private const string DEMO_ROOT = __DIR__ . '/../../';

    public function __construct(
        public string $fileName,
        public string $code,
    ) {}

    /**
     * Source code of the whole file that declares the class
     *
     * A woven class is loaded from the cache directory, its original file is shown instead
     *
     * @param ReflectionClass<*> $class
     */
    public static function ofClass(ReflectionClass $class): self
    {
        $fileName = (string) $class->getFileName();
        if ($class->implementsInterface(Proxy::class)) {
            $fileName = MagicConstantTransformer::resolveFileName($fileName);
        }

        return new self(self::relative($fileName), (string) file_get_contents($fileName));
    }

    /**
     * Body of the closure, without its signature and the closing brace
     */
    public static function ofClosure(Closure $closure): self
    {
        $function = new ReflectionFunction($closure);
        $fileName = (string) $function->getFileName();
        $lines    = file($fileName, FILE_IGNORE_NEW_LINES);
        $start    = (int) $function->getStartLine();
        $body     = array_slice($lines === false ? [] : $lines, $start, (int) $function->getEndLine() - $start - 1);

        return new self(self::relative($fileName), self::dedent($body));
    }

    private static function relative(string $fileName): string
    {
        $root = (string) realpath(self::DEMO_ROOT) . DIRECTORY_SEPARATOR;

        return str_starts_with($fileName, $root) ? substr($fileName, strlen($root)) : basename($fileName);
    }

    /**
     * @param list<string> $lines
     */
    private static function dedent(array $lines): string
    {
        $indents = [];
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $indents[] = strlen($line) - strlen(ltrim($line));
            }
        }
        $indent = $indents === [] ? 0 : min($indents);

        return implode(PHP_EOL, array_map(static fn(string $line): string => substr($line, $indent), $lines));
    }
}
