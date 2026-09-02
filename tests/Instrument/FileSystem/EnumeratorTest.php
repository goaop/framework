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

use Go\VirtualFileSystem\FileSystem;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class EnumeratorTest extends TestCase
{
    protected static FileSystem $fileSystem;

    /**
     * Set up fixture test folders and files
     *
     * @throws \Exception
     */
    public static function setUpBeforeClass(): void
    {
        static::$fileSystem = FileSystem::mount('vfs');

        $testPaths = [
            '/base/sub/test',
            '/base/sub/sub/test'
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
                ['vfs://base/sub/test', 'vfs://base/sub/sub/test'],
                [],
                []
            ],
            [
                // Exclude double sub folder
                ['vfs://base/sub/test'],
                [],
                ['vfs://base/sub/sub/test']
            ],
            [
                // Exclude double sub folder just by base path
                ['vfs://base/sub/test'],
                [],
                ['vfs://base/sub/sub']
            ],
            [
                // Exclude all, expected shout be empty
                [],
                [],
                ['vfs://base/sub/test', 'vfs://base/sub/sub/test']
            ],
            [
                // Exclude all sub using wildcard
                [],
                [],
                ['vfs://base/*/test']
            ],
            [
                // Includepath using wildcard should not break
                ['vfs://base/sub/test', 'vfs://base/sub/sub/test'],
                ['vfs://base/*'],
                []
            ]
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
            ->setConstructorArgs(['vfs://base', $includePaths, $excludePaths])
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

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Path /somewhere/base/other is not in /base');
        $enumerator->enumerate();
    }

    /**
     * Sanity check for the fixed prefix test: include paths below the root are accepted
     * (no UnexpectedValueException; Finder then fails on the nonexistent directory itself)
     */
    public function testIncludePathBelowRootDirectoryPassesTheRootCheck(): void
    {
        $enumerator = new Enumerator('vfs://base', ['vfs://base/sub']);

        $files = iterator_to_array($enumerator->enumerate());
        $this->assertNotEmpty($files);
    }
}
