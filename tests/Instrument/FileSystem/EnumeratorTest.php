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

use Go\Aop\Exception\InvalidConfigurationException;
use Go\PhpUnit\UsesTemporaryDirectory;
use Go\VirtualFileSystem\FileSystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

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
    }

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
}
