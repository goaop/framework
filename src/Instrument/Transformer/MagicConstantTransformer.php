<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\PathResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\MagicConst\File;
use PhpParser\Node\Identifier;

/**
 * Rule that replaces magic __DIR__ and __FILE__ constants in the source code
 *
 * Additionally, ReflectionClass->getFileName() is also wrapped into normalizer method call
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 */
final class MagicConstantTransformer implements PrefilteredNodeRewriter
{
    /**
     * Root path of application
     */
    protected static string $rootPath = '';

    /**
     * Path to rewrite to (cache directory)
     */
    protected static string $rewriteToPath = '';

    /**
     * Class constructor
     */
    public function __construct(AspectKernel $kernel)
    {
        self::configurePaths($kernel->getOptions());
    }

    /**
     * Remembers the path mapping used by resolveFileName()
     *
     * @phpstan-param KernelOptions $options
     */
    private static function configurePaths(array $options): void
    {
        self::$rootPath      = $options['appDir'];
        self::$rewriteToPath = $options['cacheDir'] ?? '';
    }

    /**
     * Forgets the path mapping, the next resolveFileName() configures from the booted kernel again
     *
     * @internal For tests and processes that boot the framework again
     */
    public static function reset(): void
    {
        self::$rootPath      = '';
        self::$rewriteToPath = '';
    }

    public function getNodeTypes(): array
    {
        return [Dir::class, File::class, MethodCall::class];
    }

    /**
     * __DIR__, __FILE__ and ReflectionClass::getFileName() calls
     */
    public function getSourceMarkers(): array
    {
        return ['__DIR__', '__FILE__', 'getFileName'];
    }

    /**
     * Replaces magic __DIR__ and __FILE__ constants with calculated value and wraps getFileName() calls
     *
     * Always abstains: the rewrite only matters for sources executed from the cache directory, which other
     * transformers produce. A source served unchanged runs from its original location, and PHP resolves the
     * magic constants of a `php://filter/.../resource=<path>` include to <path> itself, so they stay correct
     * on cache hits too (see the functional MagicConstantTest)
     */
    public function rewriteNode(Node $node, StreamMetaData $file, array $ancestors): bool
    {
        if ($node instanceof MethodCall) {
            $this->wrapReflectionGetFileName($node, $file);
        } elseif ($node instanceof Dir || $node instanceof File) {
            $this->replaceMagicDirFileConstant($node, $file);
        }

        return false;
    }

    /**
     * Resolves file name from the cache directory to the real application root dir
     *
     * Called at runtime from woven code, so it configures its paths on demand from the
     * booted kernel: the transformer object itself only exists on the weaving path.
     */
    public static function resolveFileName(string $fileName): string
    {
        if (self::$rootPath === '') {
            self::configurePaths(AspectKernel::getInstance()->getOptions());
        }
        $rebasedFileName = self::$rewriteToPath !== ''
            ? PathResolver::rebase($fileName, self::$rewriteToPath, self::$rootPath)
            : null;
        if ($rebasedFileName !== null) {
            $fileName = $rebasedFileName;
            // Only the trailing marker of the woven-body file is dropped, so a class that simply
            // carries the suffix word in its own name (e.g. `OriginalTraitRegistry.php`) keeps its name
            $proxiedFileSuffix = AspectContainer::ORIGINAL_TRAIT_FILE_SUFFIX;
            if (str_ends_with($fileName, $proxiedFileSuffix)) {
                $fileName = substr($fileName, 0, -strlen($proxiedFileSuffix)) . '.php';
            }
        }

        return $fileName;
    }

    /**
     * Wraps possible getFileName() method of ReflectionFile into normalizer method call
     */
    private function wrapReflectionGetFileName(MethodCall $methodCall, StreamMetaData $file): void
    {
        if (!($methodCall->name instanceof Identifier) || $methodCall->name->toString() !== 'getFileName') {
            return;
        }
        $startPosition = $methodCall->getAttribute('startTokenPos');
        $endPosition   = $methodCall->getAttribute('endTokenPos');
        if (!is_int($startPosition) || !is_int($endPosition)) {
            return;
        }
        $expressionPrefix = '\\' . self::class . '::resolveFileName(';

        $file->tokenStream[$startPosition]->text = $expressionPrefix . $file->tokenStream[$startPosition]->text;
        $file->tokenStream[$endPosition]->text .= ')';
    }

    /**
     * Replaces magic __DIR__ or __FILE__ constant with calculated value
     */
    private function replaceMagicDirFileConstant(Dir|File $magicConstant, StreamMetaData $file): void
    {
        $tokenPosition = $magicConstant->getAttribute('startTokenPos');
        if (!is_int($tokenPosition)) {
            return;
        }
        $replacement = $magicConstant instanceof Dir ? dirname($file->uri) : $file->uri;

        $file->tokenStream[$tokenPosition]->text = "'{$replacement}'";
    }
}
