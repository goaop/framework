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

use Go\Aop\PointFilter;
use Go\Aop\Support\AnnotationFilter;
use Go\Aop\Support\InheritanceClassFilter;
use Go\Aop\Support\ModifierMatcherFilter;
use Go\Aop\Support\SimpleNamespaceFilter;
use Go\Aop\Support\TruePointFilter;
use Go\Aop\Support\SimpleClassFilter;
use Go\Core\AspectContainer;
use Go\Instrument\RawAnnotationReader;
use Dissect\Parser\Grammar;

/**
 * Pointcut grammar defines general structure of pointcuts and rules of parsing
 */
class PointcutGrammar extends Grammar
{
    /**
     * Constructs a pointcut grammar with AST
     */
    public function __construct(AspectContainer $container = null, RawAnnotationReader $annotationReader = null)
    {
        $this('Empty')
            ->is(/* empty */);

        $this('Pointcut')
            ->is('Pointcut', '||', 'Pointcut')
            ->call(function ($first, $_0, $second) {
                return new OrPointcut($first, $second);
            })

            ->is('Pointcut', '&&', 'Pointcut')
            ->call(function ($first, $_0, $second) {
                return new AndPointcut($first, $second);
            })

            ->is('(', 'Pointcut', ')')
            ->call(function ($_0, $pointcut) {
                return $pointcut;
            })

            ->is('!', 'Pointcut')
            ->call(function ($_0, $first) {
                return new NotPointcut($first);
            })

            ->is('cflowbelow', '(', 'Pointcut', ')')
            ->call(function ($_0, $_1, $pointcut) {
                return new CFlowBelowMethodPointcut($pointcut);
            })

            ->is('SinglePointcut');

        $this('SinglePointcut')
            ->is(
                'execution', '(' ,
                    'MemberModifiers', 'ClassFilter', 'MethodCall', 'NamePattern', '(', '*', ')',
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
                $pointcut = new SignatureMethodPointcut($methodNamePattern, $memberModifiers);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is(
                'dynamic', '(' ,
                    'MemberModifiers', 'ClassFilter', 'MethodCall', 'NamePattern', '(', '*', ')',
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
                    'NamespacePattern', '(', '*', ')',
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

            ->is('access', '(', 'MemberModifiers', 'ClassFilter', '->', 'NamePattern', ')')
            ->call(function(
                $_0,
                $_1,
                ModifierMatcherFilter $memberModifiers,
                PointFilter $classFilter,
                $_2,
                $propertyNamePattern
            ) {
                $pointcut = new SignaturePropertyPointcut($propertyNamePattern, $memberModifiers);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is('Annotation', 'access', '(', 'NamespacePattern', ')')
            ->call(function ($_0, $_1, $_2, $annotationClassName) use ($annotationReader) {
                $kindProperty = AnnotationFilter::KIND_PROPERTY;
                return new AnnotationPointcut($kindProperty, $annotationReader, $annotationClassName);
            })

            ->is('Annotation', 'annotation', '(', 'NamespacePattern', ')')
            ->call(function ($_0, $_1, $_2, $annotationClassName) use ($annotationReader) {
                $kindMethod = AnnotationFilter::KIND_METHOD;
                return new AnnotationPointcut($kindMethod, $annotationReader, $annotationClassName);
            })

            ->is('within', '(', 'ClassFilter', ')')
            ->call(function ($_0, $_1, $classFilter) {
                $pointcut = new TrueMethodPointcut();
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is('Annotation', 'within', '(', 'NamespacePattern', ')')
            ->call(function ($_0, $_1, $_2, $annotationClassName) use ($annotationReader) {
                $pointcut    = new TrueMethodPointcut();
                $kind        = AnnotationFilter::KIND_CLASS;
                $classFilter = new AnnotationFilter($kind, $annotationReader, $annotationClassName);
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is('class', '(', 'ClassFilter', ')')
            ->call(function ($_0, $_1, $classFilter) {
                $pointcut = new TruePointcut();
                $pointcut->setClassFilter($classFilter);

                return $pointcut;
            })

            ->is('PointcutReference')
            ->call(function ($pointcutName) use ($container) {
                return $container->getPointcut($pointcutName);
            });

        $stringConverter = $this->getNodeToStringConverter();

        $this('PointcutReference')
            ->is('NamespacePattern', 'MethodCall', 'NamePart')
            ->call($stringConverter);

        // stable
        $this('MethodCall')
            ->is('::')->call($stringConverter)
            ->is('->')->call($stringConverter);

        $this('ClassFilter')
            ->is('NamespacePattern')
            ->call(function ($pattern) {
                return $pattern === '**'
                    ? TruePointFilter::getInstance()
                    : new SimpleClassFilter($pattern);
            })

            ->is('NamespacePattern', '+')
            ->call(function ($parentClassName) {
                return new InheritanceClassFilter($parentClassName);
            })
        ;

        // stable
        $this('NamespacePattern')
            ->is('NamePattern')
            ->is('**')->call($stringConverter)
            ->is('NamespacePattern', 'NsSeparator', 'NamespacePattern')->call($stringConverter);

        // stable
        $this('NamePattern')
            ->is('NamePattern', '*')->call($stringConverter)
            ->is('NamePattern', 'NamePart')->call($stringConverter)
            ->is('NamePattern', '|', 'NamePart')->call($stringConverter)
            ->is('NamePart')->call($stringConverter)
            ->is('*')->call($stringConverter);

        // stable
        $this('MemberModifiers')
            ->is('*')
            ->call(function () {
                $matcher = new ModifierMatcherFilter();

                return $matcher->orMatch(-1);
            })
            ->is('NonEmptyMemberModifiers');

        // stable
        $this('NonEmptyMemberModifiers')
            ->is('MemberModifier')
            ->call(function ($modifier) {
                return new ModifierMatcherFilter($modifier);
            })

            ->is('NonEmptyMemberModifiers','|','MemberModifier')
            ->call(function (ModifierMatcherFilter $matcher, $_0, $modifier) {
                return $matcher->orMatch($modifier);
            })

            ->is('NonEmptyMemberModifiers', 'MemberModifier')
            ->call(function (ModifierMatcherFilter $matcher, $modifier) {
                return $matcher->andMatch($modifier);
            });

        // stable
        $converter = $this->getModifierConverter();
        $this('MemberModifier')
            ->is('public')->call($converter)
            ->is('protected')->call($converter)
            ->is('private')->call(function () {
                throw new \RuntimeException("Private modifier is not supported");
            })
            ->is('final')->call($converter);

        $this->resolve(Grammar::ALL);
        $this->start('Pointcut');
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
        return function ($node) {
            $name = strtoupper($node->getValue());

            return constant("ReflectionMethod::IS_{$name}");
        };
    }
}
