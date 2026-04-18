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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
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

        if ($injectCall !== null && count($injectCall->args) >= 2) {
            $advicesNode = $injectCall->args[1]->value;
            $result      = (new ConstExprEvaluator())->evaluateSilently($advicesNode);

            return is_array($result) ? $result : [];
        }

        // Enum proxies do not use injectJoinPoints() — they use per-method static joinpoints via
        // EnumProxyGenerator::getJoinPoint(__CLASS__, 'method'|'static', 'methodName', [...advices...]).
        // Parse all getJoinPoint() calls and reconstruct the same array structure.
        /** @var StaticCall[] $getJoinPointCalls */
        $getJoinPointCalls = (new NodeFinder())->find($ast, static function ($node): bool {
            return $node instanceof StaticCall
                && $node->name instanceof Identifier
                && $node->name->toString() === 'getJoinPoint'
                && $node->class instanceof Name
                && str_ends_with($node->class->toString(), 'EnumProxyGenerator');
        });

        if (empty($getJoinPointCalls)) {
            return [];
        }

        $evaluator = new ConstExprEvaluator();
        $result    = [];
        foreach ($getJoinPointCalls as $call) {
            if (count($call->args) < 4) {
                continue;
            }
            $arg1 = $call->args[1];
            $arg2 = $call->args[2];
            $arg3 = $call->args[3];
            if (!($arg1 instanceof Arg) || !($arg2 instanceof Arg) || !($arg3 instanceof Arg)) {
                continue;
            }
            // arg[1] = join-point type string ('method' or 'static')
            $typeNode = $arg1->value;
            // arg[2] = method name string
            $nameNode = $arg2->value;
            // arg[3] = advice names array
            $advicesNode = $arg3->value;

            if (!($typeNode instanceof String_) || !($nameNode instanceof String_) || !($advicesNode instanceof Array_)) {
                continue;
            }

            $prefix      = $typeNode->value;
            $methodName  = $nameNode->value;
            $adviceNames = $evaluator->evaluateSilently($advicesNode);

            if (!is_array($adviceNames)) {
                continue;
            }

            /** @var string[] $adviceNames */
            $result[$prefix][$methodName] = array_values($adviceNames);
        }

        return $result;
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
