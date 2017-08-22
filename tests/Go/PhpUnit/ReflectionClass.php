<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\PhpUnit;

use Go\Instrument\PathResolver;
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionEngine;
use Go\ParserReflection\ReflectionFile;

final class ProxyClassReflectionHelper
{
    private function __construct()
    {
        // noop
    }

    public static function createReflectionClass($className, array $configuration)
    {
        $parsedReflectionClass = new ReflectionClass($className);
        $originalClassFile     = $parsedReflectionClass->getFileName();
        $originalNamespace     = $parsedReflectionClass->getNamespaceName();

        $fileRelativePath = substr($originalClassFile, strlen(PathResolver::realpath($configuration['appDir'])));
        $proxyFileName    = $configuration['cacheDir'] . '/_proxies' . $fileRelativePath;
        $proxyFileContent = file_get_contents($proxyFileName);

        // To prevent deep analysis of parents, we just cut everything after "extends"
        $proxyFileContent = preg_replace('/extends.*/', '', $proxyFileContent);
        $proxyFileAST     = ReflectionEngine::parseFile($proxyFileName, $proxyFileContent);

        $proxyReflectionFile  = new ReflectionFile($proxyFileName, $proxyFileAST);
        $proxyClassReflection = $proxyReflectionFile->getFileNamespace($originalNamespace)->getClass($className);

        return $proxyClassReflection;
    }
}
