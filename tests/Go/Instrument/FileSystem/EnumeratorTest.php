<?php
declare(strict_types = 1);

namespace Go\Instrument\FileSystem;

use PHPUnit\Framework\TestCase;
use Vfs\FileSystem;

class EnumeratorTest extends TestCase
{

    /** @var FileSystem */
    protected static $fileSystem;

    /**
     * Set up fixture test folders and files
     *
     * @throws \Exception
     * @return void
     */
    public static function setUpBeforeClass()
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

    public static function tearDownAfterClass()
    {
        static::$fileSystem->unmount();
    }

    /**
     * @return array
     */
    public function pathsProvider(): array
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
     * @dataProvider pathsProvider
     *
     * @param array $expectedPaths
     * @param array $includePaths
     * @param array $excludePaths
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    public function testExclude($expectedPaths, $includePaths, $excludePaths): void
    {
        $testPaths = [];

        /** @var Enumerator $mock */
        $mock = $this->getMockBuilder(Enumerator::class)
            ->setConstructorArgs(['vfs://base', $includePaths, $excludePaths])
            ->setMethods(['getFileFullPath'])
            ->getMock();

        // Mock getFileRealPath method to provide a pathname
        // VFS does not support getRealPath()
        $mock->expects($this->any())
            ->method('getFileFullPath')
            ->will($this->returnCallback(function (\SplFileInfo $file) {
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
