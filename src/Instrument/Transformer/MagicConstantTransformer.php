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
use PhpParser\Node\Arg;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\MagicConst\File;
use PhpParser\NodeVisitor;

/**
 * Transformer that replaces magic __DIR__ and __FILE__ constants in the source code
 *
 * Additionally, ReflectionClass->getFileName() is also wrapped into normalizer method call
 */
class MagicConstantTransformer extends BaseSourceTransformer implements NodeVisitor
{
    /**
     * Root path of application
     */
    protected static string $rootPath = '';

    /**
     * Path to rewrite to (cache directory)
     */
    protected static string $rewriteToPath = '';
    private bool $hasChanges = false;
    private string $currentFileName = '';

    /**
     * Class constructor
     */
    public function __construct(AspectKernel $kernel)
    {
        parent::__construct($kernel);
        self::$rootPath      = $this->options['appDir'];
        self::$rewriteToPath = $this->options['cacheDir'] ?? '';
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->hasChanges = false;

        return null;
    }

    public function enterNode(Node $node): int|Node|null
    {
        return null;
    }

    public function leaveNode(Node $node): int|Node|null
    {
        if ($node instanceof Dir) {
            $this->hasChanges = true;

            return new String_(dirname($this->currentFileName));
        }

        if ($node instanceof File) {
            $this->hasChanges = true;

            return new String_($this->currentFileName);
        }

        if ($node instanceof Node\Expr\MethodCall
            && $node->name instanceof Identifier
            && $node->name->toString() === 'getFileName'
            && !$node->getAttribute('goaop_wrapped_get_file_name')
        ) {
            $this->hasChanges = true;
            $methodCall = clone $node;
            $methodCall->setAttribute('goaop_wrapped_get_file_name', true);

            return new StaticCall(
                new FullyQualified(self::class),
                'resolveFileName',
                [new Arg($methodCall)]
            );
        }

        return null;
    }

    public function afterTraverse(array $nodes): ?array
    {
        return null;
    }

    public function hasChanges(): bool
    {
        return $this->hasChanges;
    }

    public function setCurrentFileName(string $fileName): void
    {
        $this->currentFileName = $fileName;
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

}
