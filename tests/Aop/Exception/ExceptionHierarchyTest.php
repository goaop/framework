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

namespace Go\Aop\Exception;

use Go\Aop\AspectException;
use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Finder\Finder;

final class ExceptionHierarchyTest extends TestCase
{
    /**
     * @return array<string, array{class-string<\Throwable>, class-string<\Throwable>}>
     */
    public static function exceptionTypes(): array
    {
        return [
            'aspect'               => [AspectException::class, RuntimeException::class],
            'invalid config'       => [InvalidConfigurationException::class, InvalidArgumentException::class],
            'pointcut syntax'      => [PointcutSyntaxException::class, AspectException::class],
            'service not found'    => [ServiceNotFoundException::class, OutOfBoundsException::class],
            'weaving'              => [WeavingException::class, RuntimeException::class],
            'not compilable'       => [NotCompilableException::class, AspectException::class],
        ];
    }

    /**
     * Every type implements the marker and keeps the SPL parent that existing catch blocks rely on
     *
     * @param class-string<\Throwable> $exceptionClass
     * @param class-string<\Throwable> $parentClass
     */
    #[DataProvider('exceptionTypes')]
    public function testExceptionTypeImplementsMarkerAndKeepsSplParent(string $exceptionClass, string $parentClass): void
    {
        $exception = new $exceptionClass('message');

        $this->assertInstanceOf(ExceptionInterface::class, $exception);
        $this->assertInstanceOf($parentClass, $exception);
    }

    public function testEveryThrowInSourcesUsesAFrameworkException(): void
    {
        $allowed   = ['AspectException', 'InvalidConfigurationException', 'PointcutSyntaxException',
            'ServiceNotFoundException', 'WeavingException', 'NotCompilableException',
            // Converts warnings inside the cache warmer loop, which catches it right away
            'ErrorException'];
        $offenders = [];
        foreach (Finder::create()->files()->in(__DIR__ . '/../../../src')->name('*.php') as $file) {
            preg_match_all('/throw new \\\\?([\w\\\\]+)\(/', $file->getContents(), $matches);
            foreach ($matches[1] as $thrownClass) {
                $shortName = substr((string) strrchr('\\' . $thrownClass, '\\'), 1);
                if (!in_array($shortName, $allowed, true)) {
                    $offenders[] = $file->getRelativePathname() . ': ' . $thrownClass;
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
