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
