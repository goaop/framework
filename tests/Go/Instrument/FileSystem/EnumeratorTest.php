<?php

namespace Go\Instrument;

use Go\Instrument\FileSystem\Enumerator;

class EnumeratorTest extends \PHPUnit_Framework_TestCase
{

    protected static $fixtureBasePath;

    /**
     * Set up fixture test folders and files
     *
     * @throws \Exception
     * @return void
     */
    public static function setUpBeforeClass()
    {
        if (!defined('TEST_DIRECTORY')) {
            throw new \Exception('TEST_DIRECTORY not set, check your phpunit.xml');
        }

        static::$fixtureBasePath =  realpath(TEST_DIRECTORY) . DIRECTORY_SEPARATOR . 'fixtures';

        // Also make sure nothing exists prior run
        self::tearDownAfterClass();

        if (!file_exists(static::$fixtureBasePath)) {
            mkdir(static::$fixtureBasePath);
        }

        $testPaths = [
            'base/sub/test',
            'base/sub/sub/test'
        ];

        // Setup some files we test against
        foreach ($testPaths as $path) {
            $testPath = static::$fixtureBasePath . DIRECTORY_SEPARATOR . $path;
            mkdir($testPath, 0777, true);
            touch($testPath . DIRECTORY_SEPARATOR . 'TestClass.php');
        }
    }

    /**
     * Clean fixture paths after tests are done
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        exec('rm -rf ' . static::$fixtureBasePath);
    }

    /**
     * @return array
     */
    public function pathsProvider()
    {
        return [
            [
                // No include or exclude, every folder should be there
                ['base/sub/test', 'base/sub/sub/test'],
                [],
                []
            ],
            [
                // Exclude double sub folder
                ['base/sub/test'],
                [],
                ['base/sub/sub/test']
            ],
            [
                // Exclude all, expected shout be empty
                [],
                [],
                ['base/sub/test', 'base/sub/sub/test']
            ],
            [
                // Exclude all sub using wildcard
                [],
                [],
                ['base/**/test']
            ],
            [
                // Exclude single sub using wildcard
                ['base/sub/sub/test'],
                [],
                ['base/*/test']
            ],
            [
                // Includepath using wildcard should not break
                ['base/sub/test', 'base/sub/sub/test'],
                ['base/**/'],
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
     */
    public function testExclude($expectedPaths, $includePaths, $excludePaths)
    {
        $basePath = static::$fixtureBasePath;
        $addBasePath = function ($path) use ($basePath) {
            return $basePath . DIRECTORY_SEPARATOR . $path;
        };

        $testPaths = [];
        // Path basepath to each include/excludepath
        $expectedPaths = array_map($addBasePath, $expectedPaths);
        $includePaths = array_map($addBasePath, $includePaths);
        $excludePaths = array_map($addBasePath, $excludePaths);

        $enumerator = new Enumerator(static::$fixtureBasePath, $includePaths, $excludePaths);
        $iterator = $enumerator->enumerate();

        foreach ($iterator as $file) {
            $testPaths[] = $file->getPath();
        }

        sort($testPaths);
        sort($expectedPaths);

        $this->assertEquals($expectedPaths, $testPaths);
    }
}
