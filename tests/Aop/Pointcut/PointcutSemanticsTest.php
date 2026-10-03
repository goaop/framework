<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Attribute;
use Go\Aop\Exception\PointcutSyntaxException;
use Go\Aop\Pointcut;
use PHPUnit\Framework\Attributes\DataProvider;
use Go\Stubs\ByReferenceStub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Matching semantics of parsed pointcut expressions (#686)
 */
final class PointcutSemanticsTest extends TestCase
{
    private static function parse(string $expression): Pointcut
    {
        $parser = new PointcutParser(new PointcutGrammar());

        return $parser->parse((new PointcutLexer())->lex($expression));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function modifierCases(): array
    {
        $pattern = 'execution(final public|protected ' . PointcutSemanticsTarget::class . '->*(*))';

        return [
            'final public matches'        => [$pattern, 'finalPublic', true],
            'final protected matches'     => [$pattern, 'finalProtected', true],
            'non-final public is skipped' => [$pattern, 'plainPublic', false],
            'private is skipped'          => [$pattern, 'privateMethod', false],
        ];
    }

    #[DataProvider('modifierCases')]
    public function testFinalAppliesToEveryVisibilityAlternative(string $expression, string $methodName, bool $expected): void
    {
        $class = new ReflectionClass(PointcutSemanticsTarget::class);

        $this->assertSame($expected, self::parse($expression)->matches($class, $class->getMethod($methodName)));
    }

    public function testTwoGroupsOfModifierAlternativesAreRejected(): void
    {
        $this->expectException(PointcutSyntaxException::class);

        self::parse('access(public|protected readonly|final Foo->*)');
    }

    /**
     * @return array<string, array{class-string, bool}>
     */
    public static function inheritanceCases(): array
    {
        return [
            'the type itself'            => [PointcutSemanticsBase::class, true],
            'subclass'                   => [PointcutSemanticsChild::class, true],
            'interface implementer'      => [PointcutSemanticsImplementer::class, true],
            'trait user'                 => [PointcutSemanticsTraitUser::class, true],
            'subclass of a trait user'   => [PointcutSemanticsTraitUserChild::class, true],
            'unrelated class'            => [PointcutSemanticsTarget::class, false],
        ];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('inheritanceCases')]
    public function testPlusMatchesTheTypeItsSubtypesAndTraitUsers(string $className, bool $expected): void
    {
        $base      = new ClassInheritancePointcut(PointcutSemanticsBase::class);
        $interface = new ClassInheritancePointcut(PointcutSemanticsInterface::class);
        $trait     = new ClassInheritancePointcut(PointcutSemanticsTrait::class);
        $class     = new ReflectionClass($className);

        $this->assertSame(
            $expected,
            $base->matches($class) || $interface->matches($class) || $trait->matches($class),
        );
    }

    public function testAttributePointcutMatchesAttributeSubclasses(): void
    {
        $class = new ReflectionClass(PointcutSemanticsTarget::class);

        $pointcut = self::parse('@execution(' . PointcutSemanticsMarker::class . ')');

        $this->assertTrue($pointcut->matches($class, $class->getMethod('plainPublic')));
        $this->assertFalse($pointcut->matches($class, $class->getMethod('finalPublic')));
    }

    public function testMethodsReturningByReferenceCanBeFilteredOut(): void
    {
        $class    = new ReflectionClass(ByReferenceStub::class);
        $pointcut = self::parse('execution(public ' . ByReferenceStub::class . '->*(*)) && !matchReturningByReference()');

        $this->assertFalse($pointcut->matches($class, $class->getMethod('items')));
        $this->assertTrue($pointcut->matches($class, $class->getMethod('count')));

        $byReference = self::parse('matchReturningByReference()');
        $this->assertInstanceOf(MatchReturningByReferencePointcut::class, $byReference);
        $this->assertTrue($byReference->matches($class, $class->getMethod('items')));
        $this->assertTrue($byReference->matches($class), 'Without a member the class context must stay open');
    }

    public function testNullableReturnTypeMarker(): void
    {
        $class    = new ReflectionClass(PointcutSemanticsTarget::class);
        $pointcut = self::parse('execution(public ' . PointcutSemanticsTarget::class . '->*(*): ?string)');

        $this->assertTrue($pointcut->matches($class, $class->getMethod('nullableString')));
        $this->assertFalse($pointcut->matches($class, $class->getMethod('plainPublic')));
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
class PointcutSemanticsMarker {}

#[Attribute(Attribute::TARGET_METHOD)]
final class PointcutSemanticsSpecialMarker extends PointcutSemanticsMarker {}

class PointcutSemanticsTarget
{
    #[PointcutSemanticsSpecialMarker]
    public function plainPublic(): void {}

    final public function finalPublic(): void {}

    final protected function finalProtected(): void {}

    // @phpstan-ignore method.unused (matched by reflection only)
    private function privateMethod(): void {}

    public function nullableString(): ?string
    {
        return null;
    }
}

interface PointcutSemanticsInterface {}

trait PointcutSemanticsTrait {}

class PointcutSemanticsBase {}

final class PointcutSemanticsChild extends PointcutSemanticsBase {}

final class PointcutSemanticsImplementer implements PointcutSemanticsInterface {}

class PointcutSemanticsTraitUser
{
    use PointcutSemanticsTrait;
}

final class PointcutSemanticsTraitUserChild extends PointcutSemanticsTraitUser {}
