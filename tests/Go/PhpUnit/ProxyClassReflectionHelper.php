<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\PhpUnit;

use Go\Instrument\PathResolver;
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionEngine;
use Go\ParserReflection\ReflectionFile;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;

/**
 * Utility functions that helps initialization of reflection classes that introspects classes and its members
 * by parsing its AST (without loading class into memory).
 */
final class ProxyClassReflectionHelper
{
    private function __construct()
    {
    }

    /**
     * Extracts the advice names array from the injectJoinPoints() call in the generated proxy file.
     *
     * @param string $className     Full qualified class name
     * @param array  $configuration Configuration used for Go! AOP project setup
     *
     * @return string[][][] Advice names indexed by join point type and name, or empty array if not found
     */
    public static function extractAdvicesFromProxyFile(string $className, array $configuration): array
    {
        $parsedReflectionClass = new ReflectionClass($className);
        $originalClassFile     = $parsedReflectionClass->getFileName();

        $appDir            = PathResolver::realpath($configuration['appDir']);
        $relativePath      = str_replace($appDir . DIRECTORY_SEPARATOR, '', $originalClassFile);
        $classSuffix       = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
        $proxyRelativePath = $relativePath . DIRECTORY_SEPARATOR . $classSuffix;
        $proxyFileName     = $configuration['cacheDir'] . '/_proxies/' . $proxyRelativePath;

        if (!file_exists($proxyFileName)) {
            return [];
        }

        $ast = ReflectionEngine::parseFile($proxyFileName);

        /** @var StaticCall|null $injectCall */
        $injectCall = (new NodeFinder())->findFirst($ast, static function ($node): bool {
            return $node instanceof StaticCall
                && $node->name instanceof Identifier
                && $node->name->toString() === 'injectJoinPoints'
                && $node->class instanceof Name
                && str_ends_with($node->class->toString(), 'ClassProxyGenerator');
        });

        if ($injectCall === null || count($injectCall->args) < 2) {
            return [];
        }

        $advicesNode = $injectCall->args[1]->value;
        $result      = (new ConstExprEvaluator())->evaluateSilently($advicesNode);

        return is_array($result) ? $result : [];
    }

    /**
     * Creates \Go\ParserReflection\ReflectionClass instance that introspects class without loading class into memory.
     *
     * @param string $className Full qualified class name for which \Go\ParserReflection\ReflectionClass ought to be initialized
     * @param array $configuration Configuration used for Go! AOP project setup
     */
    public static function createReflectionClass(string $className, array $configuration): ReflectionClass
    {
        $parsedReflectionClass = new ReflectionClass($className);
        $originalClassFile     = $parsedReflectionClass->getFileName();
        $originalNamespace     = $parsedReflectionClass->getNamespaceName();

        $appDir            = PathResolver::realpath($configuration['appDir']);
        $relativePath      = str_replace($appDir . DIRECTORY_SEPARATOR, '', $originalClassFile);
        $classSuffix       = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
        $proxyRelativePath = $relativePath . DIRECTORY_SEPARATOR . $classSuffix;
        $proxyFileName     = $configuration['cacheDir'] . '/_proxies/' . $proxyRelativePath;
        $proxyFileContent  = file_get_contents($proxyFileName);

        // To prevent deep analysis of parents, we just cut everything after "extends"
        $proxyFileContent = preg_replace('/extends.*/', '', $proxyFileContent);
        $proxyFileAST     = ReflectionEngine::parseFile($proxyFileName, $proxyFileContent);

        $proxyReflectionFile  = new ReflectionFile($proxyFileName, $proxyFileAST);
        $proxyClassReflection = $proxyReflectionFile->getFileNamespace($originalNamespace)->getClass($className);

        return $proxyClassReflection;
    }
}
