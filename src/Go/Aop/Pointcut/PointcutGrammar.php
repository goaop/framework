<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use Go\Aop\PointFilter;
use Go\Aop\Support\InheritanceClassFilter;
use Go\Aop\Support\ModifierMatcherFilter;
use Go\Aop\Support\TruePointFilter;
use Go\Aop\Support\SimpleClassFilter;
use Go\Core\AspectKernel;

use Dissect\Parser\Grammar;

/**
 * Pointcut grammar defines general structure of pointcuts and rules of parsing
 *
 * @package Go\Aop\Pointcut
 */
class PointcutGrammar extends Grammar
{
    /**
     * Constructs a pointcut grammar with AST
     */
    public function __construct()
    {
        $this('Empty')
            ->is(/* empty */);

        $this('Pointcut')
            ->is('Pointcut', '||', 'Pointcut')
            ->call(function($first, $_, $second) {
                return new OrPointcut($first, $second);
            })

            ->is('Pointcut', '&&', 'Pointcut')
            ->call(function($first, $_, $second) {
                return new AndPointcut($first, $second);
            })

            ->is('(', 'Pointcut', ')')
            ->call(function($_, $pointcut) {
                return $pointcut;
            })

            ->is('!', 'SinglePointcut')
            ->call(function($_, $first) {
                return new NotPointcut($first);
            })

            ->is('SinglePointcut');

        $this('SinglePointcut')
            ->is(
                'execution', '(' ,
                    'MemberModifiers', 'ClassFilter', 'MethodCall', 'NamePattern', '(', 'Arguments', ')',
                ')'
            )
            ->call(function(
                $_, // execution node
                $_, // (
                ModifierMatcherFilter $memberModifiers,
                PointFilter $classFilter,
                $methodCallType,
                $methodNamePattern,
                $_ // )
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

            ->is('access', '(', 'MemberModifiers', 'ClassFilter', '->', 'NamePattern', ')')
            ->call(function(
                $_,
                $_,
                ModifierMatcherFilter $memberModifiers,
                PointFilter $classFilter,
                $_,
                $propertyNamePattern,
                $_ // )
            ) {
                $pointcut = new SignaturePropertyPointcut($propertyNamePattern, $memberModifiers);
                $pointcut->setClassFilter($classFilter);
                return $pointcut;
            })

            ->is('@annotation', '(', 'NamespaceClassPattern', ')')
            ->call(function ($_, $_, $annotationClassName, $_) {
                // TODO: inject container as dependency
                $container = AspectKernel::getInstance()->getContainer();
                // TODO: use single annotation reader
                $reader = $container->get('aspect.annotation.raw.reader');
                return new AnnotationMethodPointcut($reader, $annotationClassName);
            })

            ->is('within', '(', 'ClassFilter', ')')
            ->call(function ($_, $_, $classFilter, $_) {
                $pointcut = new TrueMethodPointcut();
                $pointcut->setClassFilter($classFilter);
                return $pointcut;
            })

        ;

        $this('Arguments')
            ->is('Empty')
            ->is('*');

        $stringConverter = $this->getNodeToStringConverter();

        // stable
        $this('MethodCall')
            ->is('::')->call($stringConverter)
            ->is('->')->call($stringConverter);

        $this('ClassFilter')
            ->is('NamespaceClassPattern')
            ->call(function($pattern) {
                return $pattern === '**'
                    ? TruePointFilter::getInstance()
                    : new SimpleClassFilter($pattern);
            })

            ->is('NamespaceClassPattern', '+')
            ->call(function($parentClassName, $_) {
                return new InheritanceClassFilter($parentClassName);
            })
        ;

        // stable
        $this('NamespaceClassPattern')
            ->is('NamePattern')
            ->is('**')->call($stringConverter)
            ->is('NamePattern', 'NsSeparator', 'NamespaceClassPattern')->call($stringConverter);

        // stable
        $this('NamePattern')
            ->is('NamePattern', '*')->call($stringConverter)
            ->is('NamePattern', 'NamePart')->call($stringConverter)
            ->is('NamePart')->call($stringConverter)
            ->is('*')->call($stringConverter);

        // stable
        $this('MemberModifiers')
            ->is('*')
            ->call(function() {
                $matcher = new ModifierMatcherFilter();
                return $matcher->orMatch(-1);
            })
            ->is('NonEmptyMemberModifiers');

        // stable
        $this('NonEmptyMemberModifiers')
            ->is('MemberModifier')
            ->call(function($modifier) {
                return new ModifierMatcherFilter($modifier);
            })

            ->is('NonEmptyMemberModifiers','|','MemberModifier')
            ->call(function(ModifierMatcherFilter $matcher, $_, $modifier) {
                return $matcher->orMatch($modifier);
            })

            ->is('NonEmptyMemberModifiers', 'MemberModifier')
            ->call(function(ModifierMatcherFilter $matcher, $modifier) {
                return $matcher->andMatch($modifier);
            });

        // stable
        $converter = $this->getModifierConverter();
        $this('MemberModifier')
            ->is('public')->call($converter)
            ->is('protected')->call($converter)
            ->is('private')->call(function() {
                throw new \RuntimeException("Private modifier is not supported");
            })
            ->is('final')->call($converter);

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
        return function($node) {
            $name = strtoupper($node->getValue());
            return constant("ReflectionMethod::IS_{$name}");
        };
    }
}