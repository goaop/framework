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
use Go\Aop\Exception\PointcutSyntaxException;
use Go\Aop\Pointcut;
use ReflectionMethod;
use ReflectionProperty;

use function constant;

/**
 * Pointcut grammar defines general structure of pointcuts and rules of parsing
 *
 * @internal Framework implementation detail of pointcut parsing, free to change between releases
 */
final class PointcutGrammar extends Grammar
{
    /**
     * Constructs a pointcut grammar with AST
     */
    public function __construct()
    {
        $stringConverter = $this->getNodeToStringConverter();

        $this('pointcutExpression')
            ->is('pointcutExpression', '||', 'conjugatedExpression')
            ->call(fn(Pointcut $first, mixed $_0, Pointcut $second) => new OrPointcut($first, $second))
            ->is('conjugatedExpression')
        ;

        $this('conjugatedExpression')
            ->is('conjugatedExpression', '&&', 'negatedExpression')
            ->call(fn(Pointcut $first, mixed $_0, Pointcut $second) => new AndPointcut(null, $first, $second))
            ->is('negatedExpression')
        ;

        $this('negatedExpression')
            ->is('!', 'brakedExpression')
            ->call(fn(mixed $_0, Pointcut $item) => new NotPointcut($item))
            ->is('brakedExpression')
        ;

        $this('brakedExpression')
            ->is('(', 'pointcutExpression', ')')
            ->call(fn(mixed $_0, Pointcut $e, mixed $_1) => $e)
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
            ->is('matchInheritedPointcut')
            ->is('pointcutReference')
        ;

        $this('accessPointcut')
            ->is('access', '(', 'propertyAccessReference', ')')
            ->call(fn(mixed $_0, mixed $_1, Pointcut $propertyReference) => $propertyReference)
        ;

        $this('executionPointcut')
            ->is('execution', '(', 'methodExecutionReference', ')')
            ->call(fn(mixed $_0, mixed $_1, Pointcut $methodReference) => $methodReference)
            ->is('execution', '(', 'functionExecutionReference', ')')
            ->call(fn(mixed $_0, mixed $_1, Pointcut $functionReference) => $functionReference)
        ;

        $this('withinPointcut')
            ->is('within', '(', 'classFilter', ')')
            ->call(
                function (mixed $_0, mixed $_1, Pointcut $classFilter) {
                    return new AndPointcut(
                        Pointcut::KIND_ALL,
                        $classFilter,
                    );
                },
            )
        ;

        $this('annotatedAccessPointcut')
            ->is('annotation', 'access', '(', 'namespaceName', ')')
            ->call(
                function (mixed $_0, mixed $_1, mixed $_2, string $attributeClassName) {
                    return new AttributePointcut(Pointcut::KIND_PROPERTY, $attributeClassName);
                },
            )
        ;

        $this('annotatedExecutionPointcut')
            ->is('annotation', 'execution', '(', 'namespaceName', ')')
            ->call(
                function (mixed $_0, mixed $_1, mixed $_2, string $attributeClassName) {
                    return new AttributePointcut(Pointcut::KIND_METHOD, $attributeClassName);
                },
            )
        ;

        $this('annotatedWithinPointcut')
            ->is('annotation', 'within', '(', 'namespaceName', ')')
            ->call(
                function (mixed $_0, mixed $_1, mixed $_2, string $attributeClassName) {
                    return new AttributePointcut(Pointcut::KIND_ALL, $attributeClassName, true);
                },
            )
        ;

        $this('initializationPointcut')
            ->is('initialization', '(', 'classFilter', ')')
            ->call(
                function (mixed $_0, mixed $_1, Pointcut $classFilter) {
                    return new AndPointcut(
                        Pointcut::KIND_INIT | Pointcut::KIND_CLASS,
                        $classFilter,
                    );
                },
            )
        ;

        $this('staticInitializationPointcut')
            ->is('staticinitialization', '(', 'classFilter', ')')
            ->call(
                function (mixed $_0, mixed $_1, Pointcut $classFilter) {
                    return new AndPointcut(
                        Pointcut::KIND_STATIC_INIT | Pointcut::KIND_CLASS,
                        $classFilter,
                    );
                },
            )
        ;

        $this('matchInheritedPointcut')
            ->is('matchInherited', '(', ')')
            ->call(fn(mixed ...$_) => new MatchInheritedPointcut())
        ;

        $this('pointcutReference')
            ->is('namespaceName', '->', 'namePatternPart')
            ->call(fn(string $className, mixed $_0, string $name) => new PointcutReference("{$className}->{$name}"))
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
                        new NamePointcut(Pointcut::KIND_PROPERTY, $reference->memberNamePattern),
                    );
                },
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
                        new NamePointcut(Pointcut::KIND_METHOD, $reference->memberNamePattern),
                    );
                },
            )
            ->is('memberReference', '(', 'argumentList', ')', ':', 'returnTypePattern')
            ->call(
                function (ClassMemberReference $reference, mixed $_0, mixed $_1, mixed $_2, mixed $_3, string $returnType) {
                    return new AndPointcut(
                        Pointcut::KIND_METHOD,
                        $reference->classFilter,
                        $reference->visibilityFilter,
                        $reference->accessTypeFilter,
                        new NamePointcut(Pointcut::KIND_METHOD, $reference->memberNamePattern),
                        new ReturnTypePointcut($returnType),
                    );
                },
            )
        ;

        $this('functionExecutionReference')
            ->is('namespacePattern', 'nsSeparator', 'namePatternPart', '(', 'argumentList', ')')
            ->call(
                function (string $namespacePattern, mixed $_0, string $namePattern) {
                    return new AndPointcut(
                        Pointcut::KIND_FUNCTION,
                        new NamePointcut(Pointcut::KIND_FUNCTION, $namespacePattern, true),
                        new NamePointcut(Pointcut::KIND_FUNCTION, $namePattern),
                    );
                },
            )
            ->is('namespacePattern', 'nsSeparator', 'namePatternPart', '(', 'argumentList', ')', ':', 'returnTypePattern')
            ->call(
                function (string $namespacePattern, mixed $_0, string $namePattern, mixed $_1, mixed $_2, mixed $_3, mixed $_4, string $returnType) {
                    return new AndPointcut(
                        Pointcut::KIND_FUNCTION,
                        new NamePointcut(Pointcut::KIND_FUNCTION, $namespacePattern, true),
                        new ReturnTypePointcut($returnType),
                        new NamePointcut(Pointcut::KIND_FUNCTION, $namePattern),
                    );
                },
            )
        ;

        $this('memberReference')
            ->is('memberModifiers', 'classFilter', 'memberAccessType', 'namePatternPart')
            ->call(
                function (
                    ModifierPointcut $memberModifiers,
                    Pointcut         $classFilter,
                    ModifierPointcut $memberAccessType,
                    string           $namePattern,
                ) {
                    return new ClassMemberReference(
                        $classFilter,
                        $memberModifiers,
                        $memberAccessType,
                        $namePattern,
                    );
                },
            )
        ;

        $this('classFilter')
            ->is('namespacePattern')
            ->call(
                function (string $pattern) {

                    return $pattern === '**'
                        ? new TruePointcut()
                        : new NamePointcut(Pointcut::KIND_ALL, $pattern, true);
                },
            )
            ->is('namespacePattern', '+')
            ->call(fn(string $parentClassName) => new ClassInheritancePointcut($parentClassName))
        ;

        $this('argumentList')
            ->is('*');

        $this('memberAccessType')
            ->is('::')
            ->call(fn() => new ModifierPointcut(ReflectionMethod::IS_STATIC))
            ->is('->')
            ->call(fn() => new ModifierPointcut(notMask: ReflectionMethod::IS_STATIC))
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

        // Return-type patterns support union ('|') and intersection ('&') members.
        // DNF groups are written without parentheses — 'A&B|C' is equivalent to '(A&B)|C',
        // matching PHP's own type precedence; ReturnTypePointcut normalizes both forms.
        $this('returnTypeMember')
            ->is('namespaceName')
            ->is('returnTypeMember', '&', 'namespaceName')
            ->call(fn(string $left, mixed $_0, string $right) => "{$left}&{$right}")
        ;

        $this('returnTypePattern')
            ->is('returnTypeMember')
            ->is('returnTypePattern', '|', 'returnTypeMember')
            ->call(fn(string $left, mixed $_0, string $right) => "{$left}|{$right}")
            ->is('?', 'namespaceName')
            ->call(fn(mixed $_0, string $typeName) => "?{$typeName}")
        ;

        // Space-separated modifier groups must all match, '|' alternatives inside a group bind tighter:
        // 'final public|protected' is final AND (public OR protected)
        $this('memberModifiers')
            ->is('modifierGroup', 'memberModifiers')
            ->call(fn(int $group, ModifierPointcut $matcher) => self::addModifierGroup($matcher, $group))
            ->is('modifierGroup')
            ->call(fn(int $group) => self::addModifierGroup(new ModifierPointcut(), $group))
        ;

        $this('modifierGroup')
            ->is('memberModifier', '|', 'modifierGroup')
            ->call(fn(int $modifier, mixed $_0, int $group) => $modifier | $group)
            ->is('memberModifier')
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
            ->is('readonly')
            ->call(fn() => ReflectionProperty::IS_READONLY)
            ->is('private(set)')
            ->call(fn() => ReflectionProperty::IS_PRIVATE_SET)
            ->is('protected(set)')
            ->call(fn() => ReflectionProperty::IS_PROTECTED_SET)
        ;

        $this->start('pointcutExpression');
    }

    /**
     * Adds one modifier group: a single modifier is required, alternatives need any of their bits
     */
    private static function addModifierGroup(ModifierPointcut $matcher, int $group): ModifierPointcut
    {
        if (($group & ($group - 1)) === 0) {
            return $matcher->andMatch($group);
        }
        if ($matcher->hasAlternatives()) {
            throw new PointcutSyntaxException('Only one group of modifier alternatives (a|b) is supported per member pattern');
        }

        return $matcher->orMatch($group);
    }

    /**
     * Returns callable for converting node(s) to the string
     */
    private function getNodeToStringConverter(): callable
    {
        return function (mixed ...$nodes): string {
            $value = '';
            foreach ($nodes as $node) {
                if (is_string($node)) {
                    $value .= $node;
                } elseif ($node instanceof Token) {
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
                throw new PointcutSyntaxException('Token value must be a string');
            }
            $name = strtoupper($value);

            return constant("ReflectionMethod::IS_{$name}");
        };
    }
}
