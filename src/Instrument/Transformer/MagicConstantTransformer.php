<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\MagicConst\File;
use PhpParser\NodeTraverser;
use PhpParser\Node\Identifier;
use PhpParser\NodeVisitor\FindingVisitor;

/**
 * Transformer that replaces magic __DIR__ and __FILE__ constants in the source code
 *
 * Additionally, ReflectionClass->getFileName() is also wrapped into normalizer method call
 */
class MagicConstantTransformer extends BaseSourceTransformer
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
     * Registry that maps PSR-4 proxy file paths to their original source file paths.
     * Populated at runtime via registerProxyFile() calls embedded in each proxy file header.
     *
     * @var array<string, string>
     */
    private static array $proxyFileMap = [];

    /**
     * Class constructor
     */
    public function __construct(AspectKernel $kernel)
    {
        parent::__construct($kernel);
        self::$rootPath      = $this->options['appDir'];
        self::$rewriteToPath = $this->options['cacheDir'] ?? '';
    }

    /**
     * Registers the mapping from a PSR-4 proxy file path to its original source file path
     * (expressed as a path relative to the application root directory).
     * This is called from the header of each generated proxy file when it is first included.
     *
     * @param string $proxyPath          Absolute path of the proxy file (provided via __FILE__)
     * @param string $relativeSourcePath Path to the original source file relative to {@see $rootPath}
     */
    public static function registerProxyFile(string $proxyPath, string $relativeSourcePath): void
    {
        self::$proxyFileMap[$proxyPath] = $relativeSourcePath;
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     */
    public function transform(StreamMetaData $metadata): TransformerResultEnum
    {
        $this->replaceMagicDirFileConstants($metadata);
        $this->wrapReflectionGetFileName($metadata);

        // We should always vote abstain, because if there is only changes for magic constants, we can drop them
        return TransformerResultEnum::RESULT_ABSTAIN;
    }

    /**
     * Resolves file name from the cache directory to the real application root dir.
     * For PSR-4 proxy files the mapping is looked up in the runtime registry populated
     * by {@see registerProxyFile()} calls embedded in the generated proxy file headers.
     */
    public static function resolveFileName(string $fileName): string
    {
        // Fast path: PSR-4 proxy files register themselves on first include.
        // The map stores relative paths, so we reconstruct the absolute source path.
        if (isset(self::$proxyFileMap[$fileName])) {
            return rtrim(self::$rootPath, '/\\') . DIRECTORY_SEPARATOR . self::$proxyFileMap[$fileName];
        }

        $suffix = '.php';
        $pathParts = explode($suffix, str_replace(
            self::$rewriteToPath,
            self::$rootPath,
            $fileName
        ));
        // throw away any trailing path after the first .php suffix
        return $pathParts[0] . $suffix;
    }

    /**
     * Wraps all possible getFileName() methods from ReflectionFile
     */
    private function wrapReflectionGetFileName(StreamMetaData $metadata): void
    {
        $methodCallFinder = new FindingVisitor(fn(Node $node) => $node instanceof MethodCall);
        $traverser        = new NodeTraverser();
        $traverser->addVisitor($methodCallFinder);
        $traverser->traverse($metadata->syntaxTree);

        /** @var MethodCall[] $methodCalls */
        $methodCalls = $methodCallFinder->getFoundNodes();
        foreach ($methodCalls as $methodCallNode) {
            if (($methodCallNode->name instanceof Identifier) && ($methodCallNode->name->toString() === 'getFileName')) {
                $startPosition    = $methodCallNode->getAttribute('startTokenPos');
                $endPosition      = $methodCallNode->getAttribute('endTokenPos');
                if (!is_int($startPosition) || !is_int($endPosition)) {
                    continue;
                }
                $expressionPrefix = '\\' . self::class . '::resolveFileName(';

                $metadata->tokenStream[$startPosition]->text = $expressionPrefix . $metadata->tokenStream[$startPosition]->text;
                $metadata->tokenStream[$endPosition]->text .= ')';
            }

        }
    }

    /**
     * Replaces all magic __DIR__ and __FILE__ constants in the file with calculated value
     */
    private function replaceMagicDirFileConstants(StreamMetaData $metadata): void
    {
        $magicConstFinder = new FindingVisitor(fn(Node $node) => $node instanceof Dir || $node instanceof File);
        $traverser        = new NodeTraverser();
        $traverser->addVisitor($magicConstFinder);
        $traverser->traverse($metadata->syntaxTree);

        /** @var MagicConst[] $magicConstants */
        $magicConstants = $magicConstFinder->getFoundNodes();
        $magicFileValue = $metadata->uri;
        $magicDirValue  = dirname($magicFileValue);
        foreach ($magicConstants as $magicConstantNode) {
            $tokenPosition = $magicConstantNode->getAttribute('startTokenPos');
            if (!is_int($tokenPosition)) {
                continue;
            }
            $replacement = $magicConstantNode instanceof Dir ? $magicDirValue : $magicFileValue;

            $metadata->tokenStream[$tokenPosition]->text = "'{$replacement}'";
        }
    }
}
