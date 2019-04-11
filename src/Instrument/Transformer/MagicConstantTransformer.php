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
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\MagicConst\File;
use PhpParser\NodeTraverser;
use PhpParser\Node\Identifier;

/**
 * Transformer that replaces magic __DIR__ and __FILE__ constants in the source code
 *
 * Additionally, ReflectionClass->getFileName() is also wrapped into normalizer method call
 */
class MagicConstantTransformer extends BaseSourceTransformer
{
    /**
     * Root path of application
     *
     * @var string
     */
    protected static $rootPath = '';

    /**
     * Path to rewrite to (cache directory)
     *
     * @var string
     */
    protected static $rewriteToPath = '';

    /**
     * Class constructor
     */
    public function __construct(AspectKernel $kernel)
    {
        parent::__construct($kernel);
        self::$rootPath      = $this->options['appDir'];
        self::$rewriteToPath = $this->options['cacheDir'];
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @return string See RESULT_XXX constants in the interface
     */
    public function transform(StreamMetaData $metadata): string
    {
        $this->replaceMagicDirFileConstants($metadata);
        $this->wrapReflectionGetFileName($metadata);

        // We should always vote abstain, because if there is only changes for magic constants, we can drop them
        return self::RESULT_ABSTAIN;
    }

    /**
     * Resolves file name from the cache directory to the real application root dir
     */
    public static function resolveFileName(string $fileName): string
    {
        $suffix = '.php';
        $pathParts = explode($suffix, str_replace(
            [self::$rewriteToPath, DIRECTORY_SEPARATOR . '_proxies'],
            [self::$rootPath, ''],
            $fileName
        ));
        // throw away namespaced path from actual filename
        return $pathParts[0] . $suffix;
    }

    /**
     * Wraps all possible getFileName() methods from ReflectionFile
     */
    private function wrapReflectionGetFileName(StreamMetaData $metadata): void
    {
        $methodCallFinder = new NodeFinderVisitor([MethodCall::class]);
        $traverser        = new NodeTraverser();
        $traverser->addVisitor($methodCallFinder);
        $traverser->traverse($metadata->syntaxTree);

        /** @var MethodCall[] $methodCalls */
        $methodCalls = $methodCallFinder->getFoundNodes();
        foreach ($methodCalls as $methodCallNode) {
            if (($methodCallNode->name instanceof Identifier) && ($methodCallNode->name->toString() === 'getFileName')) {
                $startPosition    = $methodCallNode->getAttribute('startTokenPos');
                $endPosition      = $methodCallNode->getAttribute('endTokenPos');
                $expressionPrefix = '\\' . __CLASS__ . '::resolveFileName(';

                $metadata->tokenStream[$startPosition][1] = $expressionPrefix . $metadata->tokenStream[$startPosition][1];
                $metadata->tokenStream[$endPosition][1] .= ')';
            }

        }
    }

    /**
     * Replaces all magic __DIR__ and __FILE__ constants in the file with calculated value
     */
    private function replaceMagicDirFileConstants(StreamMetaData $metadata): void
    {
        $magicConstFinder = new NodeFinderVisitor([Dir::class, File::class]);
        $traverser        = new NodeTraverser();
        $traverser->addVisitor($magicConstFinder);
        $traverser->traverse($metadata->syntaxTree);

        /** @var MagicConst[] $magicConstants */
        $magicConstants = $magicConstFinder->getFoundNodes();
        $magicFileValue = $metadata->uri;
        $magicDirValue  = dirname($magicFileValue);
        foreach ($magicConstants as $magicConstantNode) {
            $tokenPosition = $magicConstantNode->getAttribute('startTokenPos');
            $replacement   = $magicConstantNode instanceof Dir ? $magicDirValue : $magicFileValue;

            $metadata->tokenStream[$tokenPosition][1] = "'{$replacement}'";
        }
    }
}
