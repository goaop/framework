<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\FileSystem;

use Composer\InstalledVersions;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\PhpUnit\UsesTemporaryDirectory;
use Go\VirtualFileSystem\FileSystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class EnumeratorTest extends TestCase
{
    use UsesTemporaryDirectory;

    protected static FileSystem $fileSystem;

    /**
     * Set up fixture test folders and files
     *
     * @throws \Exception
     */
    public static function setUpBeforeClass(): void
    {
        static::$fileSystem = FileSystem::mount('enumeratorvfs');

        $testPaths = [
            '/base/sub/test',
            '/base/sub/sub/test',
        ];

        // Setup some files we test against
        foreach ($testPaths as $path) {
            static::$fileSystem->createFile($path . '/TestClass.php');
        }

        // An application tree with a cache directory and vendor packages, for the pruning tests
        foreach (self::APPLICATION_FILES as $file) {
            static::$fileSystem->createFile('/app/' . $file);
        }
    }

    private const string APP = 'enumeratorvfs://app';

    private const array APPLICATION_FILES = [
        'Kernel.php',
        'README.md',
        'src/Service.php',
        'src/ServiceTest.php',
        'src/Gen.php',
        'src/Legacy/Old.php',
        'src/Legacy/Keep/Kept.php',
        'src/LegacyBridge/Bridge.php',
        'src/Generated/Proxy/ServiceProxy.php',
        'src/Generated/Model/Model.php',
        'tests/Unit/UnitTest.php',
        'var/cache/aop/src/Service.php',
        'var/cache/aop/_include.cache',
        'vendor/autoload.php',
        'vendor/acme/lib/src/Acme.php',
        'vendor/acme/lib/tests/AcmeTest.php',
        'vendor/other/pkg/src/Other.php',
        'vendor/other/pkg/src/Proxy/OtherProxy.php',
        'vendor-bin/tool/Tool.php',
    ];

    public static function tearDownAfterClass(): void
    {
        static::$fileSystem->unmount();
    }

    /**
     * @return array<array{list<string>, list<string>, list<string>}>
     */
    public static function pathsProvider(): array
    {
        return [
            [
                // No include or exclude, every folder should be there
                ['enumeratorvfs://base/sub/test', 'enumeratorvfs://base/sub/sub/test'],
                [],
                [],
            ],
            [
                // Exclude double sub folder
                ['enumeratorvfs://base/sub/test'],
                [],
                ['enumeratorvfs://base/sub/sub/test'],
            ],
            [
                // Exclude double sub folder just by base path
                ['enumeratorvfs://base/sub/test'],
                [],
                ['enumeratorvfs://base/sub/sub'],
            ],
            [
                // Exclude all, expected shout be empty
                [],
                [],
                ['enumeratorvfs://base/sub/test', 'enumeratorvfs://base/sub/sub/test'],
            ],
            [
                // Exclude all sub using wildcard
                [],
                [],
                ['enumeratorvfs://base/*/test'],
            ],
            [
                // Includepath using wildcard should not break
                ['enumeratorvfs://base/sub/test', 'enumeratorvfs://base/sub/sub/test'],
                ['enumeratorvfs://base/*'],
                [],
            ],
        ];
    }

    /**
     * Test wildcard path matching for Enumerator.
     *
     * @param list<string> $expectedPaths
     * @param list<string> $includePaths
     * @param list<string> $excludePaths
     *
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pathsProvider')]
    public function testExclude(array $expectedPaths, array $includePaths, array $excludePaths): void
    {
        $testPaths = [];

        /** @var Enumerator&\PHPUnit\Framework\MockObject\MockObject $mock */
        $mock = $this->getMockBuilder(Enumerator::class)
            ->setConstructorArgs(['enumeratorvfs://base', $includePaths, $excludePaths])
            ->onlyMethods(['getFileFullPath'])
            ->getMock();

        // Mock getFileRealPath method to provide a pathname
        // VFS does not support getRealPath()
        $mock->method('getFileFullPath')
            ->willReturnCallback(function (SplFileInfo $file) {
                return $file->getPathname();
            });

        $iterator = $mock->enumerate();

        foreach ($iterator as $file) {
            $testPaths[] = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPath());
        }

        sort($testPaths);
        sort($expectedPaths);

        $this->assertEquals($expectedPaths, $testPaths);
    }

    /**
     * Regression test: the include-path check must be a prefix test.
     *
     * The former `strpos($path, $rootDirectory, 0) === false` was a substring-anywhere
     * test, so an include path that merely CONTAINED the root directory somewhere in the
     * middle (here: '/somewhere/base/other' contains root '/base') was wrongly accepted
     * instead of being rejected as outside the root.
     */
    public function testIncludePathMerelyContainingRootDirectoryIsRejected(): void
    {
        $enumerator = new Enumerator('/base', ['/somewhere/base/other']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Path /somewhere/base/other is not in /base');
        $enumerator->enumerate();
    }

    /**
     * Sanity check for the fixed prefix test: include paths below the root are accepted
     * (no UnexpectedValueException; Finder then fails on the nonexistent directory itself)
     */
    public function testIncludePathBelowRootDirectoryPassesTheRootCheck(): void
    {
        $enumerator = new Enumerator('enumeratorvfs://base', ['enumeratorvfs://base/sub']);

        $files = iterator_to_array($enumerator->enumerate());
        $this->assertNotEmpty($files);
    }

    public function testFilterAcceptsStreamWrapperPathsWithoutRealPath(): void
    {
        $isAllowed = new Enumerator('enumeratorvfs://base')->getFilter();

        $this->assertTrue($isAllowed(new SplFileInfo('enumeratorvfs://base/sub/test/TestClass.php')));
    }

    public function testExcludePathsAreLiteralPrefixesWithWildcardsOnly(): void
    {
        $root = self::createTemporaryDirectory('enumerator-literal');
        try {
            foreach (['lib[1]+(x)', 'lib1x', 'app/Windows'] as $directory) {
                mkdir("{$root}/{$directory}", 0777, true);
                touch("{$root}/{$directory}/Service.php");
            }
            // Regex metacharacters are literal, and a backslash-separated pattern matches a slash-separated path
            $enumerator = new Enumerator($root, [], [$root . '/lib[1]+(x)', str_replace('/', '\\', $root . '/app/Win*')]);

            $found = [];
            foreach ($enumerator->enumerate() as $file) {
                $found[] = basename($file->getPath());
            }

            $this->assertSame(['lib1x'], $found);
        } finally {
            self::removeTemporaryDirectory($root);
        }
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function rootDirectoryPaths(): iterable
    {
        yield 'file below the root'                   => ['/base/src/Service.php', true];
        yield 'file below the root, Windows'          => ['/base\\src\\Service.php', true];
        yield 'sibling sharing the root name prefix'  => ['/base-old/src/Service.php', false];
        yield 'root inside another path'              => ['/other/base/src/Service.php', false];
        yield 'not a php file'                        => ['/base/src/Service.phpx', false];
        yield 'file with an upper-case extension'     => ['/base/src/Service.PHP', false];
    }

    #[DataProvider('rootDirectoryPaths')]
    public function testPathFilterAcceptsOnlyPhpFilesBelowTheRoot(string $path, bool $isAllowed): void
    {
        $this->assertSame($isAllowed, new Enumerator('/base/')->getPathFilter()($path));
    }

    public function testPathFilterOfFileSystemRootAcceptsEveryAbsolutePath(): void
    {
        $this->assertTrue(new Enumerator('/')->getPathFilter()('/any/Service.php'));
    }

    public function testPathFilterOfEmptyRootAcceptsNothing(): void
    {
        $this->assertFalse(new Enumerator('')->getPathFilter()('/any/Service.php'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function includedAndExcludedPaths(): iterable
    {
        yield 'first include path'                  => ['/base/src/Service.php', true];
        yield 'second include path'                 => ['/base/lib/Service.php', true];
        yield 'outside of the include paths'        => ['/base/vendor/Service.php', false];
        yield 'first exclude path'                  => ['/base/src/Legacy/Service.php', false];
        yield 'second exclude path, with wildcard'  => ['/base/lib/Generated/Proxy/Service.php', false];
        yield 'include path sharing an exclude prefix' => ['/base/src/LegacyBridge/Service.php', false];
    }

    #[DataProvider('includedAndExcludedPaths')]
    public function testPathFilterCombinesAllIncludeAndExcludePaths(string $path, bool $isAllowed): void
    {
        $enumerator = new Enumerator('/base', ['/base/src', '/base/lib'], ['/base/src/Legacy', '/base/lib/*/Proxy']);

        $this->assertSame($isAllowed, $enumerator->getPathFilter()($path));
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function applicationIncludeAndExcludePaths(): iterable
    {
        $app = self::APP;

        yield 'no include or exclude paths'           => [[], []];
        yield 'excluded vendor and cache directories' => [[], ["{$app}/vendor", "{$app}/var/cache/aop"]];
        yield 'exclude path with trailing separator'  => [[], ["{$app}/vendor/"]];
        yield 'exclude path with backslashes'         => [[], ['enumeratorvfs:\\\\app\\vendor\\acme']];
        yield 'exclude path as a name prefix'         => [[], ["{$app}/src/Gen", "{$app}/src/Legacy"]];
        yield 'excluded files only'                   => [[], ["{$app}/src/Service.php", "{$app}/vendor/autoload.php"]];
        yield 'wildcard below packages'               => [[], ["{$app}/vendor/*/tests", "{$app}/vendor/*/src/Proxy"]];
        yield 'wildcard matching file names only'     => [[], ["{$app}/*Test.php"]];
        yield 'wildcard matching directory prefixes'  => [[], ["{$app}/src/*Legacy", "{$app}/vendor/a*"]];
        yield 'leading wildcard'                      => [[], ['*/Proxy', '*/aop']];
        yield 'wildcard matching everything below'    => [[], ["{$app}/src/*", "{$app}/vendor*"]];
        yield 'wildcard matching everything'          => [[], ["{$app}/*"]];
        yield 'exclude below an include path'         => [["{$app}/src"], ["{$app}/src/Legacy"]];
        yield 'exclude above an include path'         => [["{$app}/src/Legacy"], ["{$app}/src"]];
        yield 'nested include paths'                  => [["{$app}/src", "{$app}/src/Legacy"], ["{$app}/src/Legacy/Keep"]];
        yield 'include paths with wildcard excludes'  => [
            ["{$app}/src", "{$app}/vendor"],
            ["{$app}/src/Generated", "{$app}/vendor/*/tests", "{$app}/vendor/other"],
        ];
    }

    /**
     * Pruning directories while walking must not change the enumerated files: the reference walks every directory
     * below the include paths and keeps the files accepted by the path filter, as the enumerator did before
     *
     * @param list<string> $includePaths
     * @param list<string> $excludePaths
     */
    #[DataProvider('applicationIncludeAndExcludePaths')]
    public function testPruningEnumeratesTheSameFilesAsTheFileFilter(array $includePaths, array $excludePaths): void
    {
        $enumerator = $this->createVirtualEnumerator(self::APP, $includePaths, $excludePaths);

        $isAllowedPath = $enumerator->getPathFilter();
        $expectedPaths = [];
        foreach (new Finder()->files()->name('*.php')->in($includePaths !== [] ? $includePaths : [self::APP]) as $file) {
            if ($isAllowedPath($file->getPathname())) {
                $expectedPaths[] = $file->getPathname();
            }
        }

        $paths = self::pathNames($enumerator);
        sort($expectedPaths);

        $this->assertSame($expectedPaths, $paths);
    }

    public function testExcludedDirectoriesAreNotWalked(): void
    {
        $visitedPaths = [];
        $enumerator   = $this->createVirtualEnumerator(
            self::APP,
            [],
            [self::APP . '/vendor', self::APP . '/var/cache/aop', self::APP . '/src/*/Proxy', self::APP . '/*Test.php'],
            $visitedPaths,
        );

        $this->assertSame(
            [
                self::APP . '/Kernel.php',
                self::APP . '/src/Gen.php',
                self::APP . '/src/Generated/Model/Model.php',
                self::APP . '/src/Legacy/Keep/Kept.php',
                self::APP . '/src/Legacy/Old.php',
                self::APP . '/src/LegacyBridge/Bridge.php',
                self::APP . '/src/Service.php',
            ],
            self::pathNames($enumerator),
        );
        // A top-level excluded directory is checked itself, but nothing below it (vendor-bin shares the vendor prefix)
        $this->assertContains(self::APP . '/vendor', $visitedPaths);
        foreach ($visitedPaths as $visitedPath) {
            $this->assertStringStartsNotWith(self::APP . '/vendor/', $visitedPath);
        }
        // A wildcard matching only some of the files below a directory does not prune it: the files below are checked
        $this->assertNotEmpty(array_filter(
            $visitedPaths,
            static fn(string $visitedPath): bool => str_starts_with($visitedPath, self::APP . '/tests/Unit/'),
        ));
    }

    public function testNestedExcludedDirectoriesAreNotWalked(): void
    {
        // symfony/finder applies prune filters to nested directories since 6.4.46, 7.4.19 and 8.1.7 (symfony/finder@716f028),
        // older versions prune top-level directories only: the enumerated files are the same, only the walk is longer
        $finderVersion = InstalledVersions::getVersion('symfony/finder') ?? '0';
        $prunesNested  = version_compare($finderVersion, '8.1.7', '>=')
            || (version_compare($finderVersion, '7.4.19', '>=') && version_compare($finderVersion, '8.0.0-dev', '<'));
        if (!$prunesNested) {
            $this->markTestSkipped("symfony/finder {$finderVersion} does not apply prune filters to nested directories");
        }

        $visitedPaths = [];
        $enumerator   = $this->createVirtualEnumerator(
            self::APP,
            [],
            [self::APP . '/vendor', self::APP . '/var/cache/aop', self::APP . '/src/*/Proxy', self::APP . '/*Test.php'],
            $visitedPaths,
        );

        self::pathNames($enumerator);
        $this->assertContains(self::APP . '/var/cache/aop', $visitedPaths);
        $this->assertContains(self::APP . '/src/Generated/Proxy', $visitedPaths);
        foreach ($visitedPaths as $visitedPath) {
            $this->assertStringStartsNotWith(self::APP . '/var/cache/aop/', $visitedPath);
            $this->assertStringStartsNotWith(self::APP . '/src/Generated/Proxy/', $visitedPath);
        }
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function directoriesAndExcludePaths(): iterable
    {
        yield 'directory below the root'             => ['/base/src', true];
        yield 'directory below the root, Windows'    => ['/base\\src\\Domain', true];
        yield 'directory with a trailing separator'  => ['/base/src/', true];
        yield 'root directory'                       => ['/base', true];
        yield 'directory above the root'             => ['/', true];
        yield 'sibling sharing the root name prefix' => ['/base-old/src', false];
        yield 'directory outside of the root'        => ['/other/base', false];
        yield 'excluded directory'                   => ['/base/vendor', false];
        yield 'excluded directory, Windows'          => ['\\base\\vendor', false];
        yield 'below an excluded directory'          => ['/base/vendor/acme/lib', false];
        yield 'sharing an exclude name prefix'       => ['/base/vendor-bin', false];
        yield 'prefix of an exclude path'            => ['/base/vend', true];
        yield 'parent of a wildcard exclude'         => ['/base/packages/acme', true];
        yield 'matching a wildcard exclude'          => ['/base/packages/acme/tests', false];
        yield 'below a wildcard exclude'             => ['/base/packages/acme/tests/Unit', false];
        yield 'wildcard matching only file names'    => ['/base/src/Test', true];
        yield 'excluded with a trailing separator'   => ['/base/var/cache', false];
        yield 'not matching a trailing separator'    => ['/base/var/cache-old', true];
    }

    #[DataProvider('directoriesAndExcludePaths')]
    public function testDirectoryFilterRejectsOnlyDirectoriesWithoutAllowedFiles(string $path, bool $isTraversable): void
    {
        $enumerator = new Enumerator('/base/', [], ['/base/vendor', '/base/packages/*/tests', '/base/*Test.php', '/base/var/cache/']);

        $this->assertSame($isTraversable, $enumerator->getDirectoryFilter()($path));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function directoriesAndIncludePaths(): iterable
    {
        yield 'include path'                     => ['/base/src', true];
        yield 'below an include path'            => ['/base/src/Domain', true];
        yield 'parent of an include path'        => ['/base/lib', true];
        yield 'sharing an include name prefix'   => ['/base/srcOld', true];
        yield 'outside of the include paths'     => ['/base/tests', false];
        yield 'outside, Windows'                 => ['\\base\\tests', false];
        yield 'literal prefix of a wildcard'     => ['/base/lib/acme', true];
        yield 'below a wildcard include path'    => ['/base/lib/acme/src/Domain', true];
        yield 'beside the literal wildcard part' => ['/base/library', false];
    }

    #[DataProvider('directoriesAndIncludePaths')]
    public function testDirectoryFilterKeepsDirectoriesThatIncludePathsCanMatch(string $path, bool $isTraversable): void
    {
        $enumerator = new Enumerator('/base', ['/base/src', '/base/lib/*/src']);

        $this->assertSame($isTraversable, $enumerator->getDirectoryFilter()($path));
    }

    public function testDirectoryFilterOfEmptyRootRejectsEverything(): void
    {
        $this->assertFalse(new Enumerator('')->getDirectoryFilter()('/any'));
    }

    /**
     * @return list<string> Sorted path names of the enumerated files
     */
    private static function pathNames(Enumerator $enumerator): array
    {
        $paths = array_map(static fn(SplFileInfo $file): string => $file->getPathname(), iterator_to_array($enumerator->enumerate(), false));
        sort($paths);

        return $paths;
    }

    /**
     * Enumerator of the virtual file system: it has no real paths, the path name is used as is
     *
     * @param list<string> $includePaths
     * @param list<string> $excludePaths
     * @param list<string> $visitedPaths Receives every path the enumerator resolves, directories included
     */
    private function createVirtualEnumerator(string $root, array $includePaths, array $excludePaths, array &$visitedPaths = []): Enumerator
    {
        /** @var Enumerator&\PHPUnit\Framework\MockObject\MockObject $enumerator */
        $enumerator = $this->getMockBuilder(Enumerator::class)
            ->setConstructorArgs([$root, $includePaths, $excludePaths])
            ->onlyMethods(['getFileFullPath'])
            ->getMock();

        $enumerator->method('getFileFullPath')
            ->willReturnCallback(static function (SplFileInfo $file) use (&$visitedPaths): string {
                $visitedPaths[] = $file->getPathname();

                return $file->getPathname();
            });

        return $enumerator;
    }
}
