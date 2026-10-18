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

namespace Go\Driver\Ffi\Exception;

use Go\Aop\Exception\ExceptionInterface;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Exception\WeavingException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Finder\Finder;

/**
 * The exceptions of the package follow the convention of src/Aop/Exception/AGENTS.md: grouped below the framework
 * exceptions, created through named constructors only
 */
#[Group('ffi')]
final class ExceptionConventionTest extends TestCase
{
    /**
     * @return array<string, array{class-string<\Throwable>, class-string<\Throwable>}>
     */
    public static function exceptionTypes(): array
    {
        return [
            'engine configuration'  => [EngineConfigurationException::class, InvalidConfigurationException::class],
            'ffi weaving'           => [FfiWeavingException::class, WeavingException::class],
            'unsupported joinpoint' => [UnsupportedJoinpointException::class, FfiWeavingException::class],
        ];
    }

    /**
     * @return array<string, array{class-string<\Throwable>}>
     */
    public static function exceptionClasses(): array
    {
        return array_map(static fn(array $types): array => [$types[0]], self::exceptionTypes());
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     * @param class-string<\Throwable> $parentClass
     */
    #[DataProvider('exceptionTypes')]
    public function testExceptionTypeImplementsTheMarkerAndKeepsItsFrameworkParent(string $exceptionClass, string $parentClass): void
    {
        $exception = new $exceptionClass('message');

        $this->assertInstanceOf(ExceptionInterface::class, $exception);
        $this->assertInstanceOf($parentClass, $exception);
    }

    public function testEveryExceptionClassOfThePackageIsListed(): void
    {
        $listed = array_column(self::exceptionTypes(), 0);
        foreach (Finder::create()->files()->in(__DIR__ . '/../../src/Exception')->name('*.php') as $file) {
            $this->assertContains(__NAMESPACE__ . '\\' . $file->getBasename('.php'), $listed);
        }
    }

    public function testExceptionsAreCreatedOnlyThroughNamedConstructors(): void
    {
        $offenders = [];
        foreach (Finder::create()->files()->in(__DIR__ . '/../../src')->exclude('Exception')->name('*.php') as $file) {
            preg_match_all('/\bnew \\\\?([\w\\\\]*Exception)\(/', $file->getContents(), $matches);
            foreach ($matches[1] as $exceptionClass) {
                $offenders[] = $file->getRelativePathname() . ': ' . $exceptionClass;
            }
        }

        $this->assertSame([], $offenders);
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    #[DataProvider('exceptionClasses')]
    public function testNamedConstructorsReturnTheirOwnClass(string $exceptionClass): void
    {
        $constructors = 0;
        foreach (new ReflectionClass($exceptionClass)->getMethods(ReflectionMethod::IS_STATIC) as $method) {
            if ($method->class !== $exceptionClass || !$method->isPublic()) {
                continue;
            }
            $constructors++;
            $returnType = $method->getReturnType();
            $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
            $this->assertContains($returnType->getName(), ['self', $exceptionClass], $exceptionClass . '::' . $method->name . '() must return self');
        }
        $this->assertGreaterThan(0, $constructors);
    }

    public function testTheMessagesNameTheConditionAndTheRemedy(): void
    {
        $tooOld = EngineConfigurationException::engineWithoutMethodTableApi('8.5.0');
        $this->assertStringContainsString('~8.4.3 || ~8.5.1', $tooOld->getMessage());
        $this->assertStringContainsString('8.5.0 is installed', $tooOld->getMessage());
        $this->assertStringContainsString('composer require lisachenko/z-engine', $tooOld->getMessage());

        $this->assertStringContainsString('composer require lisachenko/z-engine', EngineConfigurationException::engineNotInstalled()->getMessage());
        $this->assertStringContainsString('opcache.jit=off', EngineConfigurationException::jitStillOn()->getMessage());
        $this->assertStringContainsString('Features::INTERCEPT_INCLUDES', EngineConfigurationException::includeInterceptionNotSupported()->getMessage());

        $this->assertSame(
            'The ffi weaving driver cannot weave App\Entity: the class is not published in the engine class table',
            UnsupportedJoinpointException::classNotInEngineClassTable('App\Entity')->getMessage(),
        );
        $this->assertStringContainsString('join-point kind(s) prop', FfiWeavingException::unsupportedDonorKinds('App\Entity', ['prop'])->getMessage());
        $this->assertStringContainsString('outside the application root /app', FfiWeavingException::sourceOutsideApplicationRoot('/other/x.php', '/app')->getMessage());
        $this->assertStringContainsString('does not declare the donor trait App\EntityOriginalTrait', FfiWeavingException::donorNotDeclared('/cache/x.php', 'App\Entity', 'App\EntityOriginalTrait')->getMessage());
    }
}
