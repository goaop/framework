<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Closure;
use Dissect\Lexer\Token;
use Dissect\Parser\Grammar;
use Go\Aop\PointFilter;
use Go\Aop\Support\AndPointFilter;
use Go\Aop\Support\InheritanceClassFilter;
use Go\Aop\Support\ModifierMatcherFilter;
use Go\Aop\Support\ReturnTypeFilter;
use Go\Aop\Support\SimpleNamespaceFilter;
use Go\Aop\Support\TruePointFilter;
use Go\Core\AspectContainer;
use ReflectionMethod;

use function constant;

/**
 * Pointcut grammar defines general structure of pointcuts and rules of parsing
 */
class PointcutGrammar extends Grammar
{
    /**
     * Constructs a pointcut grammar with AST
     */
    public function __construct(AspectContainer $container)
    {
        $this('empty')
            ->is(/* empty */);

        $stringConverter = $this->getNodeToStringConverter();

        $this('pointcutExpression')
            ->is('pointcutExpression', '||', 'conjugatedExpression')
            ->call(fn($first, $_0, $second) => new OrPointcut($first, $second))
            ->is('conjugatedExpression')
        ;

        $this('conjugatedExpression')
            ->is('conjugatedExpression', '&&', 'negatedExpression')
            ->call(fn($first, $_0, $second) => new AndPointcut($first, $second))
            ->is('negatedExpression')
        ;

        $this('negatedExpression')
            ->is('!', 'brakedExpression')
            ->call(fn($_0, $item) => new NotPointcut($item))
            ->is('brakedExpression')
        ;

        $this('brakedExpression')
            ->is('(', 'pointcutExpression', ')')
            ->call(fn($_0, $e, $_1) => $e)
            ->is('singlePointcut')
        ;

        $this('singlePointcut')
            ->is('accessPointcut')
            ->is('annotatedAccessPointcut')
            ->is('executionPointcut')
            ->is('annotatedExecutionPointcut')
            ->is('withinPointcut')
            ->is('annotatedWithinPointcut')
            ->is('initializationPointcut')
            ->is('staticInitializationPointcut')
            ->is('cflowbelowPointcut')
            ->is('dynamicExecutionPointcut')
            ->is('matchInheritedPointcut')
            ->is('pointcutReference')
        ;

        $this('accessPointcut')
            ->is('access', '(', 'propertyAccessReference', ')')
            ->call(fn($_0, $_1, $propertyReference) => $propertyReference)
        ;

        $this('executionPointcut')
            ->is('execution', '(', 'methodExecutionReference', ')')
            ->call(fn($_0, $_1, $methodReference) => $methodReference)
            ->is('execution', '(', 'functionExecutionReference', ')')
            ->call(fn($_0, $_1, $functionReference) => $functionReference)
        ;

        $this('withinPointcut')
            ->is('within', '(', 'classFilter', ')')
            ->call(
                function ($_0, $_1, $classFilter) {
                    $pointcut = new TruePointcut(PointFilter::KIND_ALL);
                    $pointcut->setClassFilter($classFilter);

                    return $pointcut;
                }
            )
        ;

        $this('annotatedAccessPointcut')
            ->is('annotation', 'access', '(', 'namespaceName', ')')
            ->call(
                function ($_0, $_1, $_2, $attributeClassName) {
                    $kindProperty = PointFilter::KIND_PROPERTY;

                    return new AttributePointcut($kindProperty, $attributeClassName);
                }
            )
        ;

        $this('annotatedExecutionPointcut')
            ->is('annotation', 'execution', '(', 'namespaceName', ')')
            ->call(
                function ($_0, $_1, $_2, $attributeClassName) {
                    $kindMethod = PointFilter::KIND_METHOD;

                    return new AttributePointcut($kindMethod, $attributeClassName);
                }
            )
        ;

        $this('annotatedWithinPointcut')
            ->is('annotation', 'within', '(', 'namespaceName', ')')
            ->call(
                function ($_0, $_1, $_2, $attributeClassName) {
                    $pointcut    = new TruePointcut(PointFilter::KIND_ALL);
                    $kindClass   = PointFilter::KIND_CLASS;
                    $classFilter = new AttributePointcut($kindClass, $attributeClassName);
                    $pointcut->setClassFilter($classFilter);

                    return $pointcut;
                }
            )
        ;

        $this('initializationPointcut')
            ->is('initialization', '(', 'classFilter', ')')
            ->call(
                function ($_0, $_1, $classFilter) {
                    $pointcut = new TruePointcut(PointFilter::KIND_INIT + PointFilter::KIND_CLASS);
                    $pointcut->setClassFilter($classFilter);

                    return $pointcut;
                }
            )
        ;

        $this('staticInitializationPointcut')
            ->is('staticinitialization', '(', 'classFilter', ')')
            ->call(
                function ($_0, $_1, $classFilter) {
                    $pointcut = new TruePointcut(PointFilter::KIND_STATIC_INIT + PointFilter::KIND_CLASS);
                    $pointcut->setClassFilter($classFilter);

                    return $pointcut;
                }
            )
        ;

        $this('cflowbelowPointcut')
            ->is('cflowbelow', '(', 'executionPointcut', ')')
            ->call(fn($_0, $_1, $pointcut) => new CFlowBelowMethodPointcut($pointcut))
        ;

        $this('matchInheritedPointcut')
            ->is('matchInherited', '(', ')')
            ->call(fn() => new MatchInheritedPointcut())
        ;

        $this('dynamicExecutionPointcut')
            // ideally, this should be 'dynamic', 'methodExecutionReference'
            ->is('dynamic', '(', 'memberReference', '(', 'argumentList', ')', ')')
            ->call(
                function ($_0, $_1, ClassMemberReference $reference) {
                    $memberFilter = new AndPointFilter(
                        $reference->visibilityFilter,
                        $reference->accessTypeFilter
                    );

                    $pointcut = new MagicMethodPointcut($reference->memberNamePattern, $memberFilter);
                    $pointcut->setClassFilter($reference->classFilter);

                    return $pointcut;
                }
            )
        ;

        $this('pointcutReference')
            ->is('namespaceName', '->', 'namePatternPart')
            ->call(fn($className, $_0, $name) => new PointcutReference($container, "{$className}->{$name}"))
        ;

        $this('propertyAccessReference')
            ->is('memberReference')
            ->call(
                function (ClassMemberReference $reference) {
                    $memberFilter = new AndPointFilter(
                        $reference->visibilityFilter,
                        $reference->accessTypeFilter
                    );

                    $pointcut = new SignaturePointcut(
                        PointFilter::KIND_PROPERTY,
                        $reference->memberNamePattern,
                        $memberFilter
                    );

                    $pointcut->setClassFilter($reference->classFilter);

                    return $pointcut;
                }
            )
        ;

        $this('methodExecutionReference')
            ->is('memberReference', '(', 'argumentList', ')')
            ->call(
                function (ClassMemberReference $reference) {
                    $memberFilter = new AndPointFilter(
                        $reference->visibilityFilter,
                        $reference->accessTypeFilter
                    );

                    $pointcut = new SignaturePointcut(
                        PointFilter::KIND_METHOD,
                        $reference->memberNamePattern,
                        $memberFilter
                    );

                    $pointcut->setClassFilter($reference->classFilter);

                    return $pointcut;
                }
            )
            ->is('memberReference', '(', 'argumentList', ')', ':', 'namespaceName')
            ->call(
                function (ClassMemberReference $reference, $_0, $_1, $_2, $_3, $returnType) {
                    $memberFilter = new AndPointFilter(
                        $reference->visibilityFilter,
                        $reference->accessTypeFilter,
                        new ReturnTypeFilter($returnType)
                    );

                    $pointcut = new SignaturePointcut(
                        PointFilter::KIND_METHOD,
                        $reference->memberNamePattern,
                        $memberFilter
                    );

                    $pointcut->setClassFilter($reference->classFilter);

                    return $pointcut;
                }
            )
        ;

        $this('functionExecutionReference')
            ->is('namespacePattern', 'nsSeparator', 'namePatternPart', '(', 'argumentList', ')')
            ->call(
                function ($namespacePattern, $_0, $namePattern) {
                    $nsFilter = new SimpleNamespaceFilter($namespacePattern);
                    $pointcut = new FunctionPointcut($namePattern);
                    $pointcut->setNamespaceFilter($nsFilter);

                    return $pointcut;
                }
            )
            ->is('namespacePattern', 'nsSeparator', 'namePatternPart', '(', 'argumentList', ')', ':', 'namespaceName')
            ->call(
                function ($namespacePattern, $_0, $namePattern, $_1, $_2, $_3, $_4, $returnType) {
                    $nsFilter   = new SimpleNamespaceFilter($namespacePattern);
                    $typeFilter = new ReturnTypeFilter($returnType);
                    $pointcut   = new FunctionPointcut($namePattern, $typeFilter);
                    $pointcut->setNamespaceFilter($nsFilter);

                    return $pointcut;
                }
            )
        ;

        $this('memberReference')
            ->is('memberModifiers', 'classFilter', 'memberAccessType', 'namePatternPart')
            ->call(
                function (
                    ModifierMatcherFilter $memberModifiers,
                    PointFilter $classFilter,
                    ModifierMatcherFilter $memberAccessType,
                    $namePattern
                ) {
                    $reference = new ClassMemberReference(
                        $classFilter,
                        $memberModifiers,
                        $memberAccessType,
                        $namePattern
                    );

                    return $reference;
                }
            )
        ;

        $this('classFilter')
            ->is('namespacePattern')
            ->call(
                function ($pattern) {
                    $truePointFilter = TruePointFilter::getInstance();

                    return $pattern === '**'
                        ? $truePointFilter
                        : new SignaturePointcut(PointFilter::KIND_CLASS, $pattern, $truePointFilter);
                }
            )
            ->is('namespacePattern', '+')
            ->call(fn($parentClassName) => new InheritanceClassFilter($parentClassName))
        ;

        $this('argumentList')
            ->is('*');

        $this('memberAccessType')
            ->is('::')
            ->call(fn() => new ModifierMatcherFilter(ReflectionMethod::IS_STATIC))
            ->is('->')
            ->call(
                function () {
                    $modifierMatcherFilter = new ModifierMatcherFilter();
                    $modifierMatcherFilter->notMatch(ReflectionMethod::IS_STATIC);

                    return $modifierMatcherFilter;
                }
            )
        ;

        $this('namespacePattern')
            ->is('**')
            ->call($stringConverter)
            ->is('namePatternPart')
            ->is('namespacePattern', 'nsSeparator', 'namePatternPart')
            ->call($stringConverter)
            ->is('namespacePattern', 'nsSeparator', '**')
            ->call($stringConverter)
        ;

        $this('namePatternPart')
            ->is('*')
            ->call($stringConverter)
            ->is('namePart')
            ->call($stringConverter)
            ->is('namePatternPart', '*')
            ->call($stringConverter)
            ->is('namePatternPart', 'namePart')
            ->call($stringConverter)
            ->is('namePatternPart', '|', 'namePart')
            ->call($stringConverter)
        ;

        $this('namespaceName')
            ->is('namePart')
            ->call($stringConverter)
            ->is('namespaceName', 'nsSeparator', 'namePart')
            ->call($stringConverter)
        ;

        $this('memberModifiers')
            ->is('memberModifier', '|', 'memberModifiers')
            ->call(fn($modifier, $_0, ModifierMatcherFilter $matcher) => $matcher->orMatch($modifier))
            ->is('memberModifier', 'memberModifiers')
            ->call(fn($modifier, ModifierMatcherFilter $matcher) => $matcher->andMatch($modifier))
            ->is('memberModifier')
            ->call(fn($modifier) => new ModifierMatcherFilter($modifier))
        ;

        $converter = $this->getModifierConverter();
        $this('memberModifier')
            ->is('public')
            ->call($converter)
            ->is('protected')
            ->call($converter)
            ->is('private')
            ->call($converter)
            ->is('final')
            ->call($converter)
        ;

        $this->start('pointcutExpression');
    }

    /**
     * Returns callable for converting node(s) to the string
     */
    private function getNodeToStringConverter(): callable
    {
        return function (...$nodes) {
            $value = '';
            foreach ($nodes as $node) {
                if (is_scalar($node)) {
                    $value .= $node;
                } else {
                    $value .= $node->getValue();
                }
            }

            return $value;
        };
    }

    /**
     * Returns callable for converting node value for modifiers to the constant value
     */
    private function getModifierConverter(): Closure
    {
        return function (Token $token) {
            $name = strtoupper($token->getValue());

            return constant("ReflectionMethod::IS_{$name}");
        };
    }
}
