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

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\Pointcut\DNF\Parser\TokenizerParserInterface;
use Go\Aop\Pointcut\DNF\SemanticAnalyzerInterface;
use Go\Core\AspectKernel;
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;
use function in_array;

/**
 * Inheritance pointcut that matches any child for given parent or implements given interface
 */
final readonly class ClassInheritancePointcut implements Pointcut
{
    private const DNF_TOKENS = ['(', ')', '&', '|'];

    private TokenizerParserInterface $tokenizerParser;
    private SemanticAnalyzerInterface $semanticAnalyzer;

    /**
     * Inheritance class matcher constructor
     *
     * @param (string&class-string) $parentClassOrInterfaceName Parent class or interface name to match in hierarchy
     */
    public function __construct(private string $parentClassOrInterfaceName)
    {
        $this->tokenizerParser = AspectKernel::getInstance()->getContainer()->getService(
            TokenizerParserInterface::class
        );
        $this->semanticAnalyzer = AspectKernel::getInstance()->getContainer()->getService(
            SemanticAnalyzerInterface::class
        );
    }

    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction $reflector = null,
        object|string                                          $instanceOrScope = null,
        array                                                  $arguments = null
    ): bool {
        // We match only with ReflectionClass as a context
        if (!$context instanceof ReflectionClass) {
            return false;
        }

        if (!$this->isDNFType()) {
            // Otherwise, we match only if given context is child of given previously class name (either interface or class)
            return $context->isSubclassOf($this->parentClassOrInterfaceName) || in_array($this->parentClassOrInterfaceName, $context->getInterfaceNames());
        }

        return $this->checkDNFType($context);
    }

    public function getKind(): int
    {
        return self::KIND_CLASS;
    }

    private function isDNFType(): bool
    {
        return array_intersect(str_split($this->parentClassOrInterfaceName), self::DNF_TOKENS) !== [];
    }

    private function checkDNFType(ReflectionClass|ReflectionFileNamespace $context): bool
    {
        $ast = $this->tokenizerParser->parse($this->parentClassOrInterfaceName);
        return $this->semanticAnalyzer->verifyTree($ast, $context);
    }
}
