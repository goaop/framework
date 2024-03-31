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
use Vfs\FileSystem;

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
        static::$fileSystem = FileSystem::factory('vfs://');
        static::$fileSystem->mount();

        $testPaths = [
            '/base/sub/test',
            '/base/sub/sub/test'
        ];

        // Setup some files we test against
        foreach ($testPaths as $path) {
            mkdir('vfs://' . $path, 0777, true);
            touch('vfs://' . $path . DIRECTORY_SEPARATOR . 'TestClass.php');
        }
    }

    public static function tearDownAfterClass(): void
    {
        static::$fileSystem->unmount();
    }

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
     *
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pathsProvider')]
    public function testExclude(array $expectedPaths, array $includePaths, array $excludePaths): void
    {
        $testPaths = [];

        /** @var Enumerator $mock */
        $mock = $this->getMockBuilder(Enumerator::class)
            ->setConstructorArgs(['vfs://base', $includePaths, $excludePaths])
            ->onlyMethods(['getFileFullPath'])
            ->getMock();

        // Mock getFileRealPath method to provide a pathname
        // VFS does not support getRealPath()
        $mock->expects($this->any())
            ->method('getFileFullPath')
            ->will($this->returnCallback(function (SplFileInfo $file) {
                return $file->getPathname();
            }));

        $iterator = $mock->enumerate();

        foreach ($iterator as $file) {
            $testPaths[] = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPath());
        }

        sort($testPaths);
        sort($expectedPaths);

        $this->assertEquals($expectedPaths, $testPaths);
    }
}
