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
        // Proxy files use a PSR-4 layout: <cacheDir>/<Namespace/ClassName>.php
        $classSuffix   = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
        $proxyFileName = $configuration['cacheDir'] . DIRECTORY_SEPARATOR . $classSuffix;

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

        // New proxy generation path uses centralized InterceptorInjector calls.
        /** @var StaticCall[] $injectorCalls */
        $injectorCalls = (new NodeFinder())->find($ast, static function ($node): bool {
            return $node instanceof StaticCall
                && $node->name instanceof Identifier
                && $node->class instanceof Name
                && str_ends_with($node->class->toString(), 'InterceptorInjector');
        });

        if (!empty($injectorCalls)) {
            return self::extractAdvicesFromInjectorCalls($injectorCalls);
        }

        // Legacy enum proxies use per-method static joinpoints via EnumProxyGenerator::getJoinPoint().
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
     * @param StaticCall[] $injectorCalls
     * @return array<string, array<string, list<string>>>
     */
    private static function extractAdvicesFromInjectorCalls(array $injectorCalls): array
    {
        $evaluator = new ConstExprEvaluator();
        $result    = [];

        foreach ($injectorCalls as $call) {
            $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;
            if ($methodName === null) {
                continue;
            }

            $map = [
                'forMethod'        => ['target' => 'method',       'nameArg' => 1, 'advicesArg' => 2],
                'forStaticMethod'  => ['target' => 'static',       'nameArg' => 1, 'advicesArg' => 2],
                'forProperty'      => ['target' => 'prop',         'nameArg' => 1, 'advicesArg' => 2],
                'forInitialization'     => ['target' => 'init',       'nameArg' => null, 'advicesArg' => 1],
                'forStaticInitialization' => ['target' => 'staticinit', 'nameArg' => null, 'advicesArg' => 1],
            ];

            if (!isset($map[$methodName])) {
                continue;
            }

            $metadata = $map[$methodName];
            $advicesIndex = $metadata['advicesArg'];
            if (!isset($call->args[$advicesIndex]) || !($call->args[$advicesIndex] instanceof Arg)) {
                continue;
            }

            $adviceNames = $evaluator->evaluateSilently($call->args[$advicesIndex]->value);
            if (!is_array($adviceNames)) {
                continue;
            }

            $subject = 'root';
            $nameArg = $metadata['nameArg'];
            if (is_int($nameArg)) {
                if (!isset($call->args[$nameArg]) || !($call->args[$nameArg] instanceof Arg)) {
                    continue;
                }

                $subjectNode = $call->args[$nameArg]->value;
                if (!$subjectNode instanceof String_) {
                    continue;
                }
                $subject = $subjectNode->value;
            }

            /** @var list<string> $adviceNames */
            $result[$metadata['target']][$subject] = array_values($adviceNames);
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
        $originalNamespace     = $parsedReflectionClass->getNamespaceName();

        // Proxy files use a PSR-4 layout: <cacheDir>/<Namespace/ClassName>.php
        $classSuffix       = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
        $proxyFileName     = $configuration['cacheDir'] . DIRECTORY_SEPARATOR . $classSuffix;
        $proxyFileContent  = file_get_contents($proxyFileName);

        // To prevent deep analysis of parents, we just cut everything after "extends"
        $proxyFileContent = preg_replace('/extends.*/', '', $proxyFileContent);
        $proxyFileAST     = ReflectionEngine::parseFile($proxyFileName, $proxyFileContent);

        $proxyReflectionFile  = new ReflectionFile($proxyFileName, $proxyFileAST);
        $proxyClassReflection = $proxyReflectionFile->getFileNamespace($originalNamespace)->getClass($className);

        return $proxyClassReflection;
    }
}
