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

namespace Go\Driver\Ffi;

use Go\Driver\Ffi\Exception\FfiWeavingException;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Core\AspectContainer;
use Go\ParserReflection\ReflectionFile;
use Go\Driver\Ffi\Stubs\ReadonlyPoint;
use Go\PhpUnit\AssertsCompilablePhp;
use Go\Proxy\ClassProxyGenerator;
use Go\Tests\TestProject\Application\FfiChild;
use Go\Tests\TestProject\Application\FfiParent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The donor trait of the ffi driver: dispatcher bodies compiled outside the woven class
 */
#[Group('ffi')]
final class DonorTraitGeneratorTest extends TestCase
{
    use AssertsCompilablePhp;

    private const string FIXTURES = __DIR__ . '/../../../tests/Fixtures/project/src/Application/';

    /**
     * Both reflection flavours the weaver hands to the generator: the parsed source (the usual case) and native
     * reflection (fallback for a source the parser cannot handle)
     *
     * @return iterable<string, array{bool}>
     */
    public static function provideReflectionFlavours(): iterable
    {
        yield 'parsed source' => [true];
        yield 'native reflection' => [false];
    }

    #[DataProvider('provideReflectionFlavours')]
    public function testGeneratesADonorTraitNextToTheWovenClass(bool $parsed): void
    {
        [$class, $imports] = self::reflect(FfiChild::class, $parsed);
        $generator = new DonorTraitGenerator($class, [
            AspectContainer::METHOD_PREFIX => [
                'greet'       => [self::testAdvice()],
                'withDefault' => [self::testAdvice()],
                'describe'    => [self::testAdvice()],
            ],
            AspectContainer::STATIC_METHOD_PREFIX => [
                'create' => [self::testAdvice()],
            ],
        ], $imports, ClassProxyGenerator::indexMethods($class));
        $code = "<?php\ndeclare(strict_types=1);\n" . $generator->generate();

        self::assertPhpCompiles($code);
        self::assertSame(FfiChild::class . AspectContainer::ORIGINAL_TRAIT_SUFFIX, $generator->getDonorTraitName());
        self::assertStringContainsString('namespace Go\Tests\TestProject\Application;', $code);
        // A trait under the suffix of the stream driver's trait codegen: nothing of it binds before run time
        self::assertStringContainsString("trait FfiChildOriginalTrait\n", $code);
        self::assertStringNotContainsString('class FfiChild', $code);
        self::assertStringNotContainsString('extends', $code);
        // The joinpoint is created for self::class, which a trait method resolves at run time (the woven class)
        self::assertStringContainsString('self::class,', $code);
        self::assertStringNotContainsString('\Go\Tests\TestProject\Application\FfiChild::class', $code);
        // Own methods reach their previous body under the alias, inherited ones through the parent
        self::assertStringContainsString('$this->greetOriginalAlias(...)', $code);
        self::assertStringContainsString('$this->withDefaultOriginalAlias(...)', $code);
        self::assertStringContainsString('parent::describe(...)', $code);
        self::assertStringContainsString('parent::create(...)', $code);
        self::assertStringContainsString("InterceptorInjector::forStaticMethod(", $code);
        // self in the signature is spelled out, static keeps binding to the called class
        self::assertStringContainsString('?\Go\Tests\TestProject\Application\FfiChild $other = null): string', $code);
        if ($parsed) {
            // The parsed source keeps a constant default as written, native reflection only knows its value
            self::assertStringContainsString('public function withDefault(int $times = self::TIMES, ', $code);
        }
        // An inherited method keeps the class its declaration resolves to (the parsed source names the declaring
        // class like the stream driver's proxies do, native reflection the woven class), never the donor
        self::assertMatchesRegularExpression(
            '~public function describe\(\): \\\\Go\\\\Tests\\\\TestProject\\\\Application\\\\Ffi(Parent|Child)\n~',
            $code,
        );
        self::assertStringContainsString('public static function create(string $name): static', $code);
        self::assertStringNotContainsString(': self', $code);
        self::assertStringNotContainsString('?self', $code);
    }

    #[DataProvider('provideReflectionFlavours')]
    public function testOwnStaticMethodsDispatchThroughTheStaticAlias(bool $parsed): void
    {
        [$class, $imports] = self::reflect(FfiParent::class, $parsed);
        $generator = new DonorTraitGenerator($class, [
            AspectContainer::STATIC_METHOD_PREFIX => ['create' => [self::testAdvice()]],
            AspectContainer::METHOD_PREFIX        => ['describe' => [self::testAdvice()]],
        ], $imports, ClassProxyGenerator::indexMethods($class));
        $code = "<?php\n" . $generator->generate();

        self::assertPhpCompiles($code);
        self::assertStringContainsString('trait FfiParentOriginalTrait' . "\n", $code);
        self::assertStringContainsString('self::createOriginalAlias(...)', $code);
        self::assertStringContainsString('static::class', $code);
        self::assertStringContainsString('public function describe(): \Go\Tests\TestProject\Application\FfiParent', $code);
    }

    public function testAReadonlyClassGetsAPlainDonorTrait(): void
    {
        $class     = new ReflectionClass(ReadonlyPoint::class);
        $generator = new DonorTraitGenerator($class, [
            AspectContainer::METHOD_PREFIX => ['translate' => [self::testAdvice()]],
        ]);
        $code = "<?php\n" . $generator->generate();

        self::assertPhpCompiles($code);
        self::assertStringContainsString("trait ReadonlyPointOriginalTrait\n", $code);
        self::assertStringNotContainsString('readonly', $code);
        self::assertStringContainsString('public function translate(int $dx, int $dy): \\Go\\Driver\\Ffi\\Stubs\\ReadonlyPoint', $code);
    }

    public function testRefusesJoinpointKindsADonorCannotCarry(): void
    {
        [$class, $imports] = self::reflect(FfiChild::class, true);

        try {
            new DonorTraitGenerator($class, [
                AspectContainer::METHOD_PREFIX   => ['greet' => [self::testAdvice()]],
                AspectContainer::PROPERTY_PREFIX => ['calls' => [self::testAdvice()]],
                AspectContainer::INIT_PREFIX     => ['root' => [self::testAdvice()]],
            ], $imports);
            self::fail('Non-method join points must be refused');
        } catch (FfiWeavingException $exception) {
            $this->assertStringContainsString('contain the join-point kind(s) prop, init', $exception->getMessage());
        }
    }

    /**
     * @param class-string $className
     *
     * @return array{ReflectionClass<object>, array<string, string|null>}
     */
    private static function reflect(string $className, bool $parsed): array
    {
        if (!$parsed) {
            return [new ReflectionClass($className), []];
        }
        $file = new ReflectionFile(self::FIXTURES . (new ReflectionClass($className))->getShortName() . '.php');
        foreach ($file->getFileNamespaces() as $namespace) {
            foreach ($namespace->getClasses() as $class) {
                if ($class->getName() === $className) {
                    return [$class, $namespace->getNamespaceAliases()];
                }
            }
        }
        self::fail('Fixture class not found: ' . $className);
    }

    private static function testAdvice(): GeneratedInterceptor
    {
        return GeneratedInterceptor::fromAdvice('test', new BeforeInterceptor(static function (): void {}));
    }
}
