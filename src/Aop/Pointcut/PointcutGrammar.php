<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Dissect\Lexer\Token;
use Dissect\Parser\Grammar;
use Doctrine\Common\Annotations\Reader;
use Go\Aop\PointFilter;
use Go\Aop\Support\InheritanceClassFilter;
use Go\Aop\Support\ModifierMatcherFilter;
use Go\Aop\Support\SimpleNamespaceFilter;
use Go\Aop\Support\TruePointFilter;
use Go\Core\AspectContainer;

/**
 * Pointcut grammar defines general structure of pointcuts and rules of parsing
 */
class PointcutGrammar extends Grammar
{
    /**
     * Constructs a pointcut grammar with AST
     *
     * @param AspectContainer $container Instance of the container
     * @param Reader $annotationReader
     */
    public function __construct(AspectContainer $container, Reader $annotationReader)
    {
        $this('empty')
            ->is(/* empty */);

        $stringConverter = $this->getNodeToStringConverter();

        $this('pointcutExpression')
            ->is('pointcutExpression', '||', 'conjugatedExpression')
            ->call(function($first, $_0, $second) {
                return new OrPointcut($first, $second);
            })
            ->is('conjugatedExpression');

        $this('conjugatedExpression')
            ->is('conjugatedExpression', '&&', 'negatedExpression')
            ->call(function($first, $_0, $second) {
                return new AndPointcut($first, $second);
            })
            ->is('negatedExpression');

        $this('negatedExpression')
            ->is('!', 'brakedExpression')
            ->call(function($_0, $item) {
                return new NotPointcut($item);
            })
            ->is('brakedExpression');

        $this('brakedExpression')
            ->is('(', 'pointcutExpression', ')')
            ->call(function($_0, $e, $_1) {
                return $e;
            })
            ->is('singlePointcut');

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
            ->is('pointcutReference');

        $this('accessPointcut')
            ->is('access', '(', 'propertyAccessReference', ')')
            ->call(function($_0, $_1, $propertyReference) {
                return $propertyReference;
            });

        $this('executionPointcut')
            ->is('execution', '(', 'methodExecutionReference', ')')
            ->call(function($_0, $_1, $methodReference) {
                return $methodReference;
            })
            ->is('execution', '(', 'functionExecutionReference', ')')
            ->call(function($_0, $_1, $functionReference) {
                return $functionReference;
            });

        $this('withinPointcut')
            ->is('within', '(', 'classFilter', ')')
            ->call(function($_0, $_1, $classFilter) {
                $pointcut = new TruePointcut(PointFilter::KIND_ALL);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            });

        $this('annotatedAccessPointcut')
            ->is('annotation', 'access', '(', 'namespaceName', ')')
            ->call(function($_0, $_1, $_2, $annotationClassName) use ($annotationReader) {
                $kindProperty = PointFilter::KIND_PROPERTY;
                return new AnnotationPointcut($kindProperty, $annotationReader, $annotationClassName);
            });

        $this('annotatedExecutionPointcut')
            ->is('annotation', 'execution', '(', 'namespaceName', ')')
            ->call(function($_0, $_1, $_2, $annotationClassName) use ($annotationReader) {
                $kindMethod = PointFilter::KIND_METHOD;
                return new AnnotationPointcut($kindMethod, $annotationReader, $annotationClassName);
            });

        $this('annotatedWithinPointcut')
            ->is('annotation', 'within', '(', 'namespaceName', ')')
            ->call(function($_0, $_1, $_2, $annotationClassName) use ($annotationReader) {
                $pointcut    = new TruePointcut(PointFilter::KIND_ALL);
                $kindClass   = PointFilter::KIND_CLASS;
                $classFilter = new AnnotationPointcut($kindClass, $annotationReader, $annotationClassName);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            });

        $this('initializationPointcut')
            ->is('initialization', '(', 'classFilter', ')')
            ->call(function($_0, $_1, $classFilter) {
                $pointcut = new TruePointcut(PointFilter::KIND_INIT + PointFilter::KIND_CLASS);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            });

        $this('staticInitializationPointcut')
            ->is('staticinitialization', '(', 'classFilter', ')')
            ->call(function($_0, $_1, $classFilter) {
                $pointcut = new TruePointcut(PointFilter::KIND_STATIC_INIT + PointFilter::KIND_CLASS);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            });

        $this('cflowbelowPointcut')
            ->is('cflowbelow', '(', 'executionPointcut', ')')
            ->call(function($_0, $_1, $pointcut) {
                return new CFlowBelowMethodPointcut($pointcut);
            });

        $this('matchInheritedPointcut')
            ->is('matchInherited', '(', ')')
            ->call(function() {
                return new MatchInheritedPointcut();
            });

        $this('dynamicExecutionPointcut')
            // ideally, this should be 'dynamic', 'methodExecutionReference'
            ->is('dynamic', '(', 'memberReference', '(', 'argumentList', ')', ')')
            ->call(function($_0, $_1, ClassMemberReference $reference) {
                $memberFilter = $reference->getVisibilityFilter();
                $memberFilter = $memberFilter->merge($reference->getAccessTypeFilter());
                $pointcut     = new MagicMethodPointcut(
                    $reference->getMemberNamePattern(),
                    $memberFilter);
                $pointcut->setClassFilter($reference->getClassFilter());

                return $pointcut;
            });

        $this('pointcutReference')
            ->is('namespaceName', '->', 'namePatternPart')
            ->call(function($className, $_0, $name) use ($container) {
                return new PointcutReference($container, "{$className}->{$name}");
            });

        $this('propertyAccessReference')
            ->is('memberReference')
            ->call(function(ClassMemberReference $reference) {
                $memberFilter = $reference->getVisibilityFilter();
                $memberFilter = $memberFilter->merge($reference->getAccessTypeFilter());
                $pointcut     = new SignaturePointcut(
                    PointFilter::KIND_PROPERTY,
                    $reference->getMemberNamePattern(),
                    $memberFilter);

                $pointcut->setClassFilter($reference->getClassFilter());

                return $pointcut;
            });

        $this('methodExecutionReference')
            ->is('memberReference', '(', 'argumentList', ')')
            ->call(function(ClassMemberReference $reference) {

                $memberFilter = $reference->getVisibilityFilter();
                $memberFilter = $memberFilter->merge($reference->getAccessTypeFilter());
                $pointcut     = new SignaturePointcut(
                    PointFilter::KIND_METHOD,
                    $reference->getMemberNamePattern(),
                    $memberFilter);

                $pointcut->setClassFilter($reference->getClassFilter());

                return $pointcut;
            });

        $this('functionExecutionReference')
            ->is('namespacePattern', 'nsSeparator', 'namePatternPart', '(', 'argumentList', ')')
            ->call(function($namespacePattern, $_0, $namePattern) {
                $nsFilter = new SimpleNamespaceFilter($namespacePattern);
                $pointcut = new FunctionPointcut($namePattern);
                $pointcut->setNamespaceFilter($nsFilter);

                return $pointcut;
            });

        $this('memberReference')
            ->is('memberModifiers', 'classFilter', 'memberAccessType', 'namePatternPart')
            ->call(function(
                ModifierMatcherFilter $memberModifiers,
                PointFilter $classFilter,
                ModifierMatcherFilter $memberAccessType,
                $namePattern
            ) {
                $reference = new ClassMemberReference(
                    $classFilter,
                    $memberModifiers,
                    $memberAccessType,
                    $namePattern);

                return $reference;
            });

        $this('classFilter')
            ->is('namespacePattern')
            ->call(function($pattern) {
                $truePointFilter = TruePointFilter::getInstance();

                return $pattern === '**'
                    ? $truePointFilter
                    : new SignaturePointcut(PointFilter::KIND_CLASS, $pattern, $truePointFilter);
            })
            ->is('namespacePattern', '+')
            ->call(function($parentClassName) {
                return new InheritanceClassFilter($parentClassName);
            });

        $this('argumentList')
            ->is('*');

        $this('memberAccessType')
            ->is('::')
            ->call(function() {
                return new ModifierMatcherFilter(\ReflectionMethod::IS_STATIC);
            })
            ->is('->')
            ->call(function() {
                $modifierMatcherFilter = new ModifierMatcherFilter();
                $modifierMatcherFilter->notMatch(\ReflectionMethod::IS_STATIC);

                return $modifierMatcherFilter;
            });

        $this('namespacePattern')
            ->is('**')->call($stringConverter)
            ->is('namePatternPart')
            ->is('namespacePattern', 'nsSeparator', 'namePatternPart')->call($stringConverter)
            ->is('namespacePattern', 'nsSeparator', '**')->call($stringConverter);

        $this('namePatternPart')
            ->is('*')->call($stringConverter)
            ->is('namePart')->call($stringConverter)
            ->is('namePatternPart', '*')->call($stringConverter)
            ->is('namePatternPart', 'namePart')->call($stringConverter)
            ->is('namePatternPart', '|', 'namePart')->call($stringConverter);

        $this('namespaceName')
            ->is('namePart')->call($stringConverter)
            ->is('namespaceName', 'nsSeparator', 'namePart')->call($stringConverter);

        $this('memberModifiers')
            ->is('memberModifier', '|', 'memberModifiers')
            ->call(function($modifier, $_0, ModifierMatcherFilter $matcher) {
                return $matcher->orMatch($modifier);
            })
            ->is('memberModifier', 'memberModifiers')
            ->call(function($modifier, ModifierMatcherFilter $matcher) {
                return $matcher->andMatch($modifier);
            })
            ->is('memberModifier')
            ->call(function($modifier) {
                return new ModifierMatcherFilter($modifier);
            });

        $converter = $this->getModifierConverter();
        $this('memberModifier')
            ->is('public')->call($converter)
            ->is('protected')->call($converter)
            ->is('private')->call($converter)
            ->is('final')->call($converter);

        $this->start('pointcutExpression');
    }

    /**
     * Returns callable for converting node(s) to the string
     *
     * @return \Closure
     */
    private function getNodeToStringConverter()
    {
        return function() {
            $value = '';
            foreach (func_get_args() as $node) {
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
     *
     * @return \Closure
     */
    private function getModifierConverter()
    {
        return function(Token $token) {
            $name = strtoupper($token->getValue());

            return constant("ReflectionMethod::IS_{$name}");
        };
    }
}
