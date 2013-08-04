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
use Go\Aop\Support\SimpleNamespaceFilter;
use Go\Aop\Support\TruePointFilter;
use Go\Aop\Support\SimpleClassFilter;
use Go\Core\AspectContainer;
use Go\Instrument\RawAnnotationReader;

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
    public function __construct(AspectContainer $container, RawAnnotationReader $annotationReader)
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

            ->is('!', 'Pointcut')
            ->call(function($_, $first) {
                return new NotPointcut($first);
            })

            ->is('cflowbelow', '(', 'Pointcut', ')')
            ->call(function($_, $_, $pointcut) {
                return new CFlowBelowMethodPointcut($pointcut);
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

            ->is(
                'execution', '(',
                    'NamespacePattern', '(', 'Arguments', ')',
                ')'
            )
            ->call(function(
                $_, // function node
                $_, // (
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

            ->is('@access', '(', 'NamespacePattern', ')')
            ->call(function ($_, $_, $annotationClassName, $_) use ($annotationReader) {
                return new AnnotationPropertyPointcut($annotationReader, $annotationClassName);
            })

            ->is('@annotation', '(', 'NamespacePattern', ')')
            ->call(function ($_, $_, $annotationClassName, $_) use ($annotationReader) {
                return new AnnotationMethodPointcut($annotationReader, $annotationClassName);
            })

            ->is('within', '(', 'ClassFilter', ')')
            ->call(function ($_, $_, $classFilter, $_) {
                $pointcut = new TrueMethodPointcut();
                $pointcut->setClassFilter($classFilter);
                return $pointcut;
            })

            ->is('class', '(', 'ClassFilter', ')')
            ->call(function ($_, $_, $classFilter, $_) {
                $pointcut = new TruePointcut();
                $pointcut->setClassFilter($classFilter);
                return $pointcut;
            })

            ->is('PointcutReference')
            ->call(function ($pointcutName) use ($container) {
                return $container->getPointcut($pointcutName);
            });

        $this('Arguments')
            ->is('Empty')
            ->is('*');

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
            ->call(function($pattern) {
                return $pattern === '**'
                    ? TruePointFilter::getInstance()
                    : new SimpleClassFilter($pattern);
            })

            ->is('NamespacePattern', '+')
            ->call(function($parentClassName, $_) {
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
        return function($node) {
            $name = strtoupper($node->getValue());
            return constant("ReflectionMethod::IS_{$name}");
        };
    }
}