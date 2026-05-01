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

use Composer\Autoload\ClassLoader;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\AopComposerLoader;
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
     * Cached Composer ClassLoader instance, used for resolving proxy file paths to original sources.
     */
    private static ?ClassLoader $composerLoader = null;

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
     *
     * Two cases are handled:
     *  1. Woven (trait) cache files — identified by the {@see AspectContainer::AOP_PROXIED_SUFFIX}
     *     in their name. The cache-to-app directory substitution plus suffix stripping recovers
     *     the original source path.
     *  2. Proxy class cache files — FQCN-based paths that may differ from the PSR-4 source path
     *     when the application's PSR-4 namespace root is not the same as `appDir`. In this case
     *     Composer's ClassLoader is used to resolve the original file.
     */
    public static function resolveFileName(string $fileName): string
    {
        $suffix = '.php';
        $pathParts = explode($suffix, str_replace(
            self::$rewriteToPath,
            self::$rootPath,
            $fileName
        ));
        $baseName = $pathParts[0];

        // Case 1: woven trait file — strip the __AopProxied suffix to get the original source path.
        if (str_ends_with($baseName, AspectContainer::AOP_PROXIED_SUFFIX)) {
            return substr($baseName, 0, -strlen(AspectContainer::AOP_PROXIED_SUFFIX)) . $suffix;
        }

        // Case 2: proxy class file (FQCN-based path in the cache directory).
        // Derive the FQCN from the path and ask Composer for the canonical source file.
        if (str_starts_with($fileName, self::$rewriteToPath)) {
            $relPath = ltrim(substr($fileName, strlen(self::$rewriteToPath)), '/\\');
            // Remove .php extension and convert path separators to namespace separators
            $fqcn = str_replace('/', '\\', substr($relPath, 0, -strlen($suffix)));
            $loader = self::getComposerLoader();
            if ($loader !== null) {
                $file = $loader->findFile($fqcn);
                if ($file !== false) {
                    return realpath($file) ?: $file;
                }
            }
        }

        return $baseName . $suffix;
    }

    /**
     * Returns the Composer ClassLoader, cached after the first successful lookup.
     * When AOP is active, the ClassLoader is wrapped by AopComposerLoader — in that case
     * the original loader is accessed via {@see AopComposerLoader::getOriginalClassLoader()}.
     */
    private static function getComposerLoader(): ?ClassLoader
    {
        if (self::$composerLoader !== null) {
            return self::$composerLoader;
        }
        // When AOP is active, the original ClassLoader is wrapped by AopComposerLoader
        $loader = AopComposerLoader::getOriginalClassLoader();
        if ($loader !== null) {
            return self::$composerLoader = $loader;
        }
        // When AOP is not yet active, find the ClassLoader directly in the autoload stack
        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && isset($autoloader[0]) && $autoloader[0] instanceof ClassLoader) {
                return self::$composerLoader = $autoloader[0];
            }
        }

        return null;
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
