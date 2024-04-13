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
use Go\Aop\Pointcut;
use Go\Core\AspectContainer;
use ReflectionMethod;
use function constant;

/**
 * Pointcut grammar defines general structure of pointcuts and rules of parsing
 */
final class PointcutGrammar extends Grammar
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
            ->call(fn($first, $_0, $second) => new AndPointcut(null, $first, $second))
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
                    return new AndPointcut(
                        Pointcut::KIND_ALL,
                        $classFilter
                    );
                }
            )
        ;

        $this('annotatedAccessPointcut')
            ->is('annotation', 'access', '(', 'namespaceName', ')')
            ->call(
                function ($_0, $_1, $_2, $attributeClassName) {
                    return new AttributePointcut(Pointcut::KIND_PROPERTY, $attributeClassName);
                }
            )
        ;

        $this('annotatedExecutionPointcut')
            ->is('annotation', 'execution', '(', 'namespaceName', ')')
            ->call(
                function ($_0, $_1, $_2, $attributeClassName) {
                    return new AttributePointcut(Pointcut::KIND_METHOD, $attributeClassName);
                }
            )
        ;

        $this('annotatedWithinPointcut')
            ->is('annotation', 'within', '(', 'namespaceName', ')')
            ->call(
                function ($_0, $_1, $_2, $attributeClassName) {
                    return new AttributePointcut(Pointcut::KIND_ALL, $attributeClassName, true);
                }
            )
        ;

        $this('initializationPointcut')
            ->is('initialization', '(', 'classFilter', ')')
            ->call(
                function ($_0, $_1, $classFilter) {
                    return new AndPointcut(
                        Pointcut::KIND_INIT | Pointcut::KIND_CLASS,
                        $classFilter
                    );
                }
            )
        ;

        $this('staticInitializationPointcut')
            ->is('staticinitialization', '(', 'classFilter', ')')
            ->call(
                function ($_0, $_1, $classFilter) {
                    return new AndPointcut(
                        Pointcut::KIND_STATIC_INIT | Pointcut::KIND_CLASS,
                        $classFilter
                    );
                }
            )
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
                    $pointcut = new AndPointcut(
                        Pointcut::KIND_METHOD | Pointcut::KIND_DYNAMIC,
                        $reference->classFilter,
                        $reference->visibilityFilter,
                        $reference->accessTypeFilter,
                        new MagicMethodDynamicPointcut($reference->memberNamePattern)
                    );

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
                    return new AndPointcut(
                        Pointcut::KIND_PROPERTY,
                        $reference->classFilter,
                        $reference->visibilityFilter,
                        $reference->accessTypeFilter,
                        new NamePointcut(Pointcut::KIND_PROPERTY, $reference->memberNamePattern)
                    );
                }
            )
        ;

        $this('methodExecutionReference')
            ->is('memberReference', '(', 'argumentList', ')')
            ->call(
                function (ClassMemberReference $reference) {
                    return new AndPointcut(
                        Pointcut::KIND_METHOD,
                        $reference->classFilter,
                        $reference->visibilityFilter,
                        $reference->accessTypeFilter,
                        new NamePointcut(Pointcut::KIND_METHOD, $reference->memberNamePattern)
                    );
                }
            )
            ->is('memberReference', '(', 'argumentList', ')', ':', 'namespaceName')
            ->call(
                function (ClassMemberReference $reference, $_0, $_1, $_2, $_3, $returnType) {
                    return new AndPointcut(
                        Pointcut::KIND_METHOD,
                        $reference->classFilter,
                        $reference->visibilityFilter,
                        $reference->accessTypeFilter,
                        new NamePointcut(Pointcut::KIND_METHOD, $reference->memberNamePattern),
                        new ReturnTypePointcut($returnType),
                    );
                }
            )
        ;

        $this('functionExecutionReference')
            ->is('namespacePattern', 'nsSeparator', 'namePatternPart', '(', 'argumentList', ')')
            ->call(
                function ($namespacePattern, $_0, $namePattern) {
                    return new AndPointcut(
                        Pointcut::KIND_FUNCTION,
                        new NamePointcut(Pointcut::KIND_FUNCTION, $namespacePattern, true),
                        new NamePointcut(Pointcut::KIND_FUNCTION, $namePattern),
                    );
                }
            )
            ->is('namespacePattern', 'nsSeparator', 'namePatternPart', '(', 'argumentList', ')', ':', 'namespaceName')
            ->call(
                function ($namespacePattern, $_0, $namePattern, $_1, $_2, $_3, $_4, $returnType) {
                    return new AndPointcut(
                        Pointcut::KIND_FUNCTION,
                        new NamePointcut(Pointcut::KIND_FUNCTION, $namespacePattern, true),
                        new ReturnTypePointcut($returnType),
                        new NamePointcut(Pointcut::KIND_FUNCTION, $namePattern),
                    );
                }
            )
        ;

        $this('memberReference')
            ->is('memberModifiers', 'classFilter', 'memberAccessType', 'namePatternPart')
            ->call(
                function (
                    ModifierPointcut $memberModifiers,
                    Pointcut         $classFilter,
                    ModifierPointcut $memberAccessType,
                    string           $namePattern
                ) {
                    return new ClassMemberReference(
                        $classFilter,
                        $memberModifiers,
                        $memberAccessType,
                        $namePattern
                    );
                }
            )
        ;

        $this('classFilter')
            ->is('namespacePattern')
            ->call(
                function ($pattern) {

                    return $pattern === '**'
                        ? new TruePointcut()
                        : new NamePointcut(Pointcut::KIND_ALL, $pattern, true);
                }
            )
            ->is('namespacePattern', '+')
            ->call(fn($parentClassName) => new ClassInheritancePointcut($parentClassName))
        ;

        $this('argumentList')
            ->is('*');

        $this('memberAccessType')
            ->is('::')
            ->call(fn() => new ModifierPointcut(ReflectionMethod::IS_STATIC))
            ->is('->')
            ->call(
                function () {
                    $modifierMatcherFilter = new ModifierPointcut();
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
            ->call(fn($modifier, $_0, ModifierPointcut $matcher) => $matcher->orMatch($modifier))
            ->is('memberModifier', 'memberModifiers')
            ->call(fn($modifier, ModifierPointcut $matcher) => $matcher->andMatch($modifier))
            ->is('memberModifier')
            ->call(fn($modifier) => new ModifierPointcut($modifier))
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
            $value = $token->getValue();
            if (!is_string($value)) {
                throw new \InvalidArgumentException('Token value must be a string');
            }
            $name = strtoupper($value);

            return constant("ReflectionMethod::IS_{$name}");
        };
    }
}
