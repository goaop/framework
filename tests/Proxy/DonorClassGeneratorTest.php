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

namespace Go\Proxy;

use Go\Aop\Exception\WeavingException;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Core\AspectContainer;
use Go\ParserReflection\ReflectionFile;
use Go\PhpUnit\AssertsCompilablePhp;
use Go\Stubs\ZEngine\ReadonlyPoint;
use Go\Tests\TestProject\Application\ZEngineChild;
use Go\Tests\TestProject\Application\ZEngineParent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The donor class of the z-engine driver: dispatcher bodies compiled outside the woven class
 */
final class DonorClassGeneratorTest extends TestCase
{
    use AssertsCompilablePhp;

    private const string FIXTURES = __DIR__ . '/../Fixtures/project/src/Application/';

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
    public function testGeneratesAnAbstractDonorNextToTheWovenClass(bool $parsed): void
    {
        [$class, $imports] = self::reflect(ZEngineChild::class, $parsed);
        $generator = new DonorClassGenerator($class, [
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
        self::assertSame(ZEngineChild::class . AspectContainer::DONOR_CLASS_SUFFIX, $generator->getDonorClassName());
        self::assertStringContainsString('namespace Go\Tests\TestProject\Application;', $code);
        // Never instantiated, same parent as the woven class (parent:: must resolve), no final modifier
        self::assertStringContainsString(
            'abstract class ZEngineChild__AopDonor extends \Go\Tests\TestProject\Application\ZEngineParent',
            $code,
        );
        self::assertStringNotContainsString('final', $code);
        // The joinpoint is created for the woven class, named explicitly (self::class would be the donor)
        self::assertStringContainsString('\Go\Tests\TestProject\Application\ZEngineChild::class,', $code);
        self::assertStringNotContainsString('self::class', $code);
        // Own methods reach their previous body under the alias, inherited ones through the parent
        self::assertStringContainsString('$this->greetOriginalAlias(...)', $code);
        self::assertStringContainsString('$this->withDefaultOriginalAlias(...)', $code);
        self::assertStringContainsString('parent::describe(...)', $code);
        self::assertStringContainsString('parent::create(...)', $code);
        self::assertStringContainsString("InterceptorInjector::forStaticMethod(", $code);
        // self in the signature is spelled out, static keeps binding to the called class
        self::assertStringContainsString('?\Go\Tests\TestProject\Application\ZEngineChild $other = null): string', $code);
        if ($parsed) {
            // The parsed source keeps a constant default as written, native reflection only knows its value
            self::assertStringContainsString('public function withDefault(int $times = self::TIMES, ', $code);
        }
        // An inherited method keeps the class its declaration resolves to (the parsed source names the declaring
        // class like the stream driver's proxies do, native reflection the woven class), never the donor
        self::assertMatchesRegularExpression(
            '~public function describe\(\): \\\\Go\\\\Tests\\\\TestProject\\\\Application\\\\ZEngine(Parent|Child)\n~',
            $code,
        );
        self::assertStringContainsString('public static function create(string $name): static', $code);
        self::assertStringNotContainsString(': self', $code);
        self::assertStringNotContainsString('?self', $code);
    }

    #[DataProvider('provideReflectionFlavours')]
    public function testOwnStaticMethodsDispatchThroughTheStaticAlias(bool $parsed): void
    {
        [$class, $imports] = self::reflect(ZEngineParent::class, $parsed);
        $generator = new DonorClassGenerator($class, [
            AspectContainer::STATIC_METHOD_PREFIX => ['create' => [self::testAdvice()]],
            AspectContainer::METHOD_PREFIX        => ['describe' => [self::testAdvice()]],
        ], $imports, ClassProxyGenerator::indexMethods($class));
        $code = "<?php\n" . $generator->generate();

        self::assertPhpCompiles($code);
        self::assertStringContainsString('abstract class ZEngineParent__AopDonor' . "\n", $code);
        self::assertStringContainsString('self::createOriginalAlias(...)', $code);
        self::assertStringContainsString('static::class', $code);
        self::assertStringContainsString('public function describe(): \Go\Tests\TestProject\Application\ZEngineParent', $code);
    }

    public function testAReadonlyClassGetsAReadonlyDonor(): void
    {
        $class     = new ReflectionClass(ReadonlyPoint::class);
        $generator = new DonorClassGenerator($class, [
            AspectContainer::METHOD_PREFIX => ['translate' => [self::testAdvice()]],
        ]);
        $code = "<?php\n" . $generator->generate();

        self::assertPhpCompiles($code);
        self::assertStringContainsString('abstract readonly class ReadonlyPoint__AopDonor', $code);
        self::assertStringContainsString('public function translate(int $dx, int $dy): \\Go\\Stubs\\ZEngine\\ReadonlyPoint', $code);
    }

    public function testRefusesJoinpointKindsADonorCannotCarry(): void
    {
        [$class, $imports] = self::reflect(ZEngineChild::class, true);

        try {
            new DonorClassGenerator($class, [
                AspectContainer::METHOD_PREFIX   => ['greet' => [self::testAdvice()]],
                AspectContainer::PROPERTY_PREFIX => ['calls' => [self::testAdvice()]],
                AspectContainer::INIT_PREFIX     => ['root' => [self::testAdvice()]],
            ], $imports);
            self::fail('Non-method join points must be refused');
        } catch (WeavingException $exception) {
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
