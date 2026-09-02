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
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;

/**
 * Utility functions that helps initialization of reflection classes that introspects classes and its members
 * by parsing its AST (without loading class into memory).
 *
 * @phpstan-type ProjectConfiguration array{
 *     kernel: class-string,
 *     console: string,
 *     frontController: string,
 *     appDir: string,
 *     debug: bool,
 *     cacheDir: string,
 *     includePaths: list<string>
 * }
 */
final class ProxyClassReflectionHelper
{
    private function __construct() {}

    /**
     * Extracts the advice names array from the injectJoinPoints() call in the generated proxy file.
     *
     * @param string $className     Full qualified class name
     * @param ProjectConfiguration $configuration Configuration used for Go! AOP project setup
     *
     * @return string[][][] Advice names indexed by join point type and name, or empty array if not found
     */
    public static function extractAdvicesFromProxyFile(string $className, array $configuration): array
    {
        $parsedReflectionClass = new ReflectionClass($className);
        $originalClassFile     = $parsedReflectionClass->getFileName();
        assert(is_string($originalClassFile));

        $appDir = PathResolver::realpath($configuration['appDir']);
        assert(is_string($appDir));
        $relativePath = str_replace($appDir . DIRECTORY_SEPARATOR, '', $originalClassFile);
        $proxyFileName = $configuration['cacheDir'] . '/' . str_replace('\\', '/', $relativePath);

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
            $advicesArg = $injectCall->args[1];
            if ($advicesArg instanceof Arg) {
                $result = (new ConstExprEvaluator())->evaluateSilently($advicesArg->value);
                if (is_array($result)) {
                    /** @var string[][][] $result The advice names literal from the generated proxy is trusted */
                    return $result;
                }
            }

            return [];
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
            return self::extractAdvicesFromInjectorCalls($injectorCalls, self::extractUseAliases($ast));
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
     * @param array<string, string> $useAliases
     * @return array<string, array<string, list<string>>>
     */
    private static function extractAdvicesFromInjectorCalls(array $injectorCalls, array $useAliases): array
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

            $advicesNode = $call->args[$advicesIndex]->value;
            $adviceNames = self::extractAdviceNamesFromGeneratedFactories($advicesNode, $useAliases);
            if ($adviceNames === []) {
                $adviceNames = $evaluator->evaluateSilently($advicesNode);
            }
            if (!is_array($adviceNames) || $adviceNames === []) {
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

            /** @var array<string> $adviceNames */
            $result[$metadata['target']][$subject] = array_values($adviceNames);
        }

        return $result;
    }

    /**
     * @param Node[] $ast
     * @return array<string, string>
     */
    private static function extractUseAliases(array $ast): array
    {
        $uses = [];
        /** @var Use_[] $useNodes */
        $useNodes = (new NodeFinder())->findInstanceOf($ast, Use_::class);
        foreach ($useNodes as $useNode) {
            foreach ($useNode->uses as $useUse) {
                $fqcn = $useUse->name->toString();
                $alias = $useUse->alias?->toString() ?? substr($fqcn, (int) strrpos($fqcn, '\\') + 1);
                $uses[$alias] = $fqcn;
            }
        }

        return $uses;
    }

    /**
     * @param array<string, string> $useAliases
     * @return list<string>
     */
    private static function extractAdviceNamesFromGeneratedFactories(mixed $advicesNode, array $useAliases): array
    {
        if (!$advicesNode instanceof Array_) {
            return [];
        }

        $advisorNames = [];
        foreach ($advicesNode->items as $item) {
            $factoryCall = $item->value;
            if (!$factoryCall instanceof StaticCall || !$factoryCall->class instanceof Name || !$factoryCall->name instanceof Identifier) {
                continue;
            }
            if (!str_ends_with($factoryCall->class->toString(), 'Interceptor') || !isset($factoryCall->args[0]) || !$factoryCall->args[0] instanceof Arg) {
                continue;
            }
            // Lazy factory shape: Interceptor::before(AspectClass::class, 'adviceMethod', ...)
            $aspectClassArg = $factoryCall->args[0]->value;
            if ($aspectClassArg instanceof ClassConstFetch && $aspectClassArg->class instanceof Name) {
                $methodNameArg = isset($factoryCall->args[1]) && $factoryCall->args[1] instanceof Arg
                    ? $factoryCall->args[1]->value
                    : null;
                if ($methodNameArg instanceof String_) {
                    $aspectName     = $aspectClassArg->class->toString();
                    $advisorNames[] = ($useAliases[$aspectName] ?? $aspectName) . '->' . $methodNameArg->value;
                }

                continue;
            }

            $adviceCall = $factoryCall->args[0]->value;
            if (!$adviceCall instanceof MethodCall || !$adviceCall->name instanceof Identifier) {
                continue;
            }
            $aspectCall = $adviceCall->var;
            if (!$aspectCall instanceof StaticCall || !$aspectCall->class instanceof Name || !$aspectCall->name instanceof Identifier || !str_ends_with($aspectCall->class->toString(), 'The') || !isset($aspectCall->args[0]) || !$aspectCall->args[0] instanceof Arg) {
                continue;
            }

            if ($aspectCall->name->toString() === 'advice') {
                $advisorId = $aspectCall->args[0]->value;
                if ($advisorId instanceof String_) {
                    $advisorNames[] = $advisorId->value;
                }

                continue;
            }

            if ($aspectCall->name->toString() !== 'aspect') {
                continue;
            }

            $aspectClassConst = $aspectCall->args[0]->value;
            if (!$aspectClassConst instanceof ClassConstFetch || !$aspectClassConst->class instanceof Name) {
                continue;
            }

            $aspectName = $aspectClassConst->class->toString();
            $advisorNames[] = ($useAliases[$aspectName] ?? $aspectName) . '->' . $adviceCall->name->toString();
        }

        return $advisorNames;
    }

    /**
     * Creates \Go\ParserReflection\ReflectionClass instance that introspects class without loading class into memory.
     *
     * @param string $className Full qualified class name for which \Go\ParserReflection\ReflectionClass ought to be initialized
     * @param ProjectConfiguration $configuration Configuration used for Go! AOP project setup
     */
    public static function createReflectionClass(string $className, array $configuration): ReflectionClass
    {
        $parsedReflectionClass = new ReflectionClass($className);
        $originalClassFile     = $parsedReflectionClass->getFileName();
        assert(is_string($originalClassFile));
        $originalNamespace     = $parsedReflectionClass->getNamespaceName();

        $appDir = PathResolver::realpath($configuration['appDir']);
        assert(is_string($appDir));
        $relativePath   = str_replace($appDir . DIRECTORY_SEPARATOR, '', $originalClassFile);
        $proxyFileName  = $configuration['cacheDir'] . '/' . str_replace('\\', '/', $relativePath);
        $proxyFileContent  = file_get_contents($proxyFileName);
        assert($proxyFileContent !== false);

        // To prevent deep analysis of parents, we just cut everything after "extends"
        $proxyFileContent = preg_replace('/extends.*/', '', $proxyFileContent);
        $proxyFileAST     = ReflectionEngine::parseFile($proxyFileName, $proxyFileContent);

        $proxyReflectionFile  = new ReflectionFile($proxyFileName, $proxyFileAST);
        $proxyClassReflection = $proxyReflectionFile->getFileNamespace($originalNamespace)->getClass($className);

        return $proxyClassReflection;
    }
}
