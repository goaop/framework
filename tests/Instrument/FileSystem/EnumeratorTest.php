<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\FileSystem;

use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class EnumeratorTest extends TestCase
{
    protected static Filesystem $filesystem;

    protected static string $tempBaseDir;

    /**
     * Set up fixture test folders and files
     *
     * @throws \Exception
     */
    public static function setUpBeforeClass(): void
    {
        static::$filesystem = new Filesystem();
        static::$tempBaseDir = sys_get_temp_dir() . '/go-aop-enum-test-' . uniqid();

        $testPaths = [
            '/base/sub/test',
            '/base/sub/sub/test'
        ];

        // Setup some files we test against
        foreach ($testPaths as $path) {
            static::$filesystem->mkdir(static::$tempBaseDir . $path, 0777);
            touch(static::$tempBaseDir . $path . DIRECTORY_SEPARATOR . 'TestClass.php');
        }
    }

    public static function tearDownAfterClass(): void
    {
        static::$filesystem->remove(static::$tempBaseDir);
    }

    public static function pathsProvider(): array
    {
        $base = sys_get_temp_dir() . '/go-aop-enum-test-';
        // Paths use a placeholder that will be resolved at runtime via the test method
        return [
            [
                // No include or exclude, every folder should be there
                ['{BASE}/base/sub/test', '{BASE}/base/sub/sub/test'],
                [],
                []
            ],
            [
                // Exclude double sub folder
                ['{BASE}/base/sub/test'],
                [],
                ['{BASE}/base/sub/sub/test']
            ],
            [
                // Exclude double sub folder just by base path
                ['{BASE}/base/sub/test'],
                [],
                ['{BASE}/base/sub/sub']
            ],
            [
                // Exclude all, expected shout be empty
                [],
                [],
                ['{BASE}/base/sub/test', '{BASE}/base/sub/sub/test']
            ],
            [
                // Exclude all sub using wildcard
                [],
                [],
                ['{BASE}/base/*/test']
            ],
            [
                // Includepath using wildcard should not break
                ['{BASE}/base/sub/test', '{BASE}/base/sub/sub/test'],
                ['{BASE}/base/*'],
                []
            ]
        ];
    }

    /**
     * Test wildcard path matching for Enumerator.
     *
     *
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pathsProvider')]
    public function testExclude(array $expectedPaths, array $includePaths, array $excludePaths): void
    {
        $base = static::$tempBaseDir;
        $resolve = static fn(array $paths): array => array_map(
            static fn(string $p): string => str_replace('{BASE}', $base, $p),
            $paths,
        );

        $expectedPaths = $resolve($expectedPaths);
        $includePaths  = $resolve($includePaths);
        $excludePaths  = $resolve($excludePaths);

        $testPaths = [];

        $enumerator = new Enumerator($base . '/base', $includePaths, $excludePaths);
        $iterator   = $enumerator->enumerate();

        foreach ($iterator as $file) {
            $testPaths[] = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPath());
        }

        sort($testPaths);
        sort($expectedPaths);

        $this->assertEquals($expectedPaths, $testPaths);
    }
}
