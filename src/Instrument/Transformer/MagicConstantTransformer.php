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
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitorAbstract;

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
        $metadata->refreshSyntaxTreeFromTokenStream();
        $cloningTraverser = new NodeTraverser();
        $cloningTraverser->addVisitor(new CloningVisitor());
        $newSyntaxTree = $cloningTraverser->traverse($metadata->syntaxTree);

        $magicFileValue = $metadata->uri;
        $magicDirValue  = dirname($magicFileValue);
        $visitor = new class ($magicDirValue, $magicFileValue) extends NodeVisitorAbstract {
            public bool $hasChanges = false;

            public function __construct(
                private readonly string $magicDirValue,
                private readonly string $magicFileValue
            ) {
            }

            public function leaveNode(Node $node): ?Node
            {
                if ($node instanceof Dir) {
                    $this->hasChanges = true;

                    return new String_($this->magicDirValue);
                }

                if ($node instanceof File) {
                    $this->hasChanges = true;

                    return new String_($this->magicFileValue);
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
                        new FullyQualified(MagicConstantTransformer::class),
                        'resolveFileName',
                        [new Arg($methodCall)]
                    );
                }

                return null;
            }
        };

        $rewritingTraverser = new NodeTraverser();
        $rewritingTraverser->addVisitor($visitor);
        $newSyntaxTree = $rewritingTraverser->traverse($newSyntaxTree);

        if ($visitor->hasChanges) {
            $metadata->applySyntaxTree($newSyntaxTree);
        }

        // We should always vote abstain, because if there is only changes for magic constants, we can drop them
        return TransformerResultEnum::RESULT_ABSTAIN;
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
