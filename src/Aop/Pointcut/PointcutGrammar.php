<?php
/**
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
use Go\Aop\PointFilter;
use Go\Aop\Support\InheritanceClassFilter;
use Go\Aop\Support\ModifierMatcherFilter;
use Go\Aop\Support\SimpleNamespaceFilter;
use Go\Aop\Support\TruePointFilter;
use Go\Core\AspectContainer;
use Go\Instrument\RawAnnotationReader;

/**
 * Pointcut grammar defines general structure of pointcuts and rules of parsing
 */
class PointcutGrammar extends Grammar
{
    /**
     * Constructs a pointcut grammar with AST
     *
     * @param AspectContainer $container Instance of the container
     * @param RawAnnotationReader $annotationReader
     */
    public function __construct(AspectContainer $container = null, RawAnnotationReader $annotationReader = null)
    {
        $this('empty')
            ->is(/* empty */);

        $this('pointcut')
            ->is('pointcut', '||', 'pointcut')
            ->call(function ($first, $_0, $second) {
                return new OrPointcut($first, $second);
            })

            ->is('pointcut', '&&', 'pointcut')
            ->call(function ($first, $_0, $second) {
                return new AndPointcut($first, $second);
            })

            ->is('(', 'pointcut', ')')
            ->call(function ($_0, $pointcut) {
                return $pointcut;
            })

            ->is('!', 'pointcut')
            ->call(function ($_0, $first) {
                return new NotPointcut($first);
            })

            ->is('cflowbelow', '(', 'pointcut', ')')
            ->call(function ($_0, $_1, $pointcut) {
                return new CFlowBelowMethodPointcut($pointcut);
            })

            ->is('singlePointcut');

        $this('singlePointcut')
            ->is(
                'execution', '(' ,
                    'memberModifiers', 'classFilter', 'methodCall', 'namePattern', '(', '*', ')',
                ')'
            )
            ->call(function(
                $_0, // execution node
                $_1, // (
                ModifierMatcherFilter $memberModifiers,
                PointFilter $classFilter,
                $methodCallType,
                $methodNamePattern
            ) {
                if ($methodCallType === '::') {
                    $memberModifiers->andMatch(\ReflectionMethod::IS_STATIC);
                } else {
                    $memberModifiers->notMatch(\ReflectionMethod::IS_STATIC);
                }
                $filterKind = PointFilter::KIND_METHOD;
                $pointcut   = new SignaturePointcut($filterKind, $methodNamePattern, $memberModifiers);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is(
                'dynamic', '(' ,
                    'memberModifiers', 'classFilter', 'methodCall', 'namePattern', '(', '*', ')',
                ')'
            )
            ->call(function(
                $_0, // execution node
                $_1, // (
                ModifierMatcherFilter $memberModifiers,
                PointFilter $classFilter,
                $methodCallType,
                $methodNamePattern
            ) {
                if ($methodCallType === '::') {
                    $memberModifiers->andMatch(\ReflectionMethod::IS_STATIC);
                } else {
                    $memberModifiers->notMatch(\ReflectionMethod::IS_STATIC);
                }
                $pointcut = new MagicMethodPointcut($methodNamePattern, $memberModifiers);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is(
                'execution', '(',
                    'namespacePattern', '(', '*', ')',
                ')'
            )
            ->call(function(
                $_0, // function node
                $_1, // (
                $namespacePattern
            ) {
                $lastNsPos   = strrpos($namespacePattern, '\\');
                $namespace   = substr($namespacePattern, 0, $lastNsPos);
                $funcPattern = substr($namespacePattern, $lastNsPos+1);
                $nsFilter    = new SimpleNamespaceFilter($namespace);
                $pointcut    = new FunctionPointcut($funcPattern);
                $pointcut->setNamespaceFilter($nsFilter);

                return $pointcut;
            })

            ->is('access', '(', 'memberModifiers', 'classFilter', '->', 'namePattern', ')')
            ->call(function(
                $_0,
                $_1,
                ModifierMatcherFilter $memberModifiers,
                PointFilter $classFilter,
                $_2,
                $propertyNamePattern
            ) {
                $filterKind = PointFilter::KIND_PROPERTY;
                $pointcut   = new SignaturePointcut($filterKind, $propertyNamePattern, $memberModifiers);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is('within', '(', 'classFilter', ')')
            ->call(function ($_0, $_1, $classFilter) {
                $pointcut = new TruePointcut(PointFilter::KIND_ALL);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is('annotation', 'access', '(', 'namespacePattern', ')')
            ->call(function ($_0, $_1, $_2, $annotationClassName) use ($annotationReader) {
                $kindProperty = PointFilter::KIND_PROPERTY;
                return new AnnotationPointcut($kindProperty, $annotationReader, $annotationClassName);
            })

            ->is('annotation', 'execution', '(', 'namespacePattern', ')')
            ->call(function ($_0, $_1, $_2, $annotationClassName) use ($annotationReader) {
                $kindMethod = PointFilter::KIND_METHOD;
                return new AnnotationPointcut($kindMethod, $annotationReader, $annotationClassName);
            })

            ->is('annotation', 'within', '(', 'namespacePattern', ')')
            ->call(function ($_0, $_1, $_2, $annotationClassName) use ($annotationReader) {
                $pointcut    = new TruePointcut(PointFilter::KIND_ALL);
                $kindClass   = PointFilter::KIND_CLASS;
                $classFilter = new AnnotationPointcut($kindClass, $annotationReader, $annotationClassName);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is('initialization', '(', 'classFilter', ')')
            ->call(function ($_0, $_1, $classFilter){
                $pointcut = new TruePointcut(PointFilter::KIND_INIT + PointFilter::KIND_CLASS);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is('staticinitialization', '(', 'classFilter', ')')
            ->call(function ($_0, $_1, $classFilter) {
                $pointcut = new TruePointcut(PointFilter::KIND_STATIC_INIT + PointFilter::KIND_CLASS);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is('pointcutReference')
            ->call(function ($pointcutName) use ($container) {
                return $container->getPointcut($pointcutName);
            });

        $stringConverter = $this->getNodeToStringConverter();

        $this('pointcutReference')
            ->is('namespacePattern', 'methodCall', 'namePart')
            ->call($stringConverter);

        // stable
        $this('methodCall')
            ->is('::')->call($stringConverter)
            ->is('->')->call($stringConverter);

        $this('classFilter')
            ->is('namespacePattern')
            ->call(function ($pattern) {
                $filterKind      = PointFilter::KIND_CLASS;
                $truePointFilter = TruePointFilter::getInstance();

                return $pattern === '**'
                    ? $truePointFilter
                    : new SignaturePointcut($filterKind, $pattern, $truePointFilter);
            })

            ->is('namespacePattern', '+')
            ->call(function ($parentClassName) {
                return new InheritanceClassFilter($parentClassName);
            })
        ;

        // stable
        $this('namespacePattern')
            ->is('namePattern')
            ->is('**')->call($stringConverter)
            ->is('namespacePattern', 'nsSeparator', 'namespacePattern')->call($stringConverter);

        // stable
        $this('namePattern')
            ->is('namePattern', '*')->call($stringConverter)
            ->is('namePattern', 'namePart')->call($stringConverter)
            ->is('namePattern', '|', 'namePart')->call($stringConverter)
            ->is('namePart')->call($stringConverter)
            ->is('*')->call($stringConverter);

        // stable
        $this('memberModifiers')
            ->is('*')
            ->call(function () {
                $matcher = new ModifierMatcherFilter();

                return $matcher->orMatch(-1);
            })
            ->is('nonEmptyMemberModifiers');

        // stable
        $this('nonEmptyMemberModifiers')
            ->is('memberModifier')
            ->call(function ($modifier) {
                return new ModifierMatcherFilter($modifier);
            })

            ->is('nonEmptyMemberModifiers','|','memberModifier')
            ->call(function (ModifierMatcherFilter $matcher, $_0, $modifier) {
                return $matcher->orMatch($modifier);
            })

            ->is('nonEmptyMemberModifiers', 'memberModifier')
            ->call(function (ModifierMatcherFilter $matcher, $modifier) {
                return $matcher->andMatch($modifier);
            });

        // stable
        $converter = $this->getModifierConverter();
        $this('memberModifier')
            ->is('public')->call($converter)
            ->is('protected')->call($converter)
            ->is('private')->call($converter)
            ->is('final')->call($converter);

        $this->resolve(Grammar::ALL);
        $this->start('pointcut');
    }

    /**
     * Returns callable for converting node(s) to the string
     *
     * @return callable
     */
    private function getNodeToStringConverter()
    {
        return function () {
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
     * @return callable
     */
    private function getModifierConverter()
    {
        return function (Token $token) {
            $name = strtoupper($token->getValue());

            return constant("ReflectionMethod::IS_{$name}");
        };
    }
}
