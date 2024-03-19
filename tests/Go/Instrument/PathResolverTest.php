<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument;

use PHPUnit\Framework\TestCase;

class PathResolverTest extends TestCase
{
    /**
     * Test existence checking
     */
    public function testCanResolveAndCheckExistence(): void
    {
        $this->assertEquals(__DIR__, PathResolver::realpath(__DIR__, true));
        $this->assertEquals(false, PathResolver::realpath(__DIR__ . '/bad/dir', true));
    }

    /**
     * Test multiple resolve
     */
    public function testCanResolveArray(): void
    {
        $this->assertEquals([__DIR__ , __FILE__], PathResolver::realpath([__DIR__ , __FILE__]));
    }

    /**
     * Test for checking the logic of custom realpath() implementation
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('realpathExamples')]
    public function testRealpathWorkingCorrectly(string $path, string $expected): void
    {
        // Trick to get scheme name and path in one action. If no scheme, then there will be only one part
        $components = explode('://', $expected, 2);
        [$pathScheme, $localPath] = isset($components[1]) ? $components : [null, $components[0]];

        // resolve path parts (single dot, double dot and double delimiters)
        $localPath  = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $localPath);
        if ($pathScheme) {
            $localPath = "$pathScheme://$localPath";
        }

        $actual = PathResolver::realpath($path);
        $this->assertEquals($localPath, $actual);
    }

    /**
     * Test paths provider
     *
     * @return array
     */
    public static function realpathExamples(): array
    {
        $curDir = getcwd();
        $parent = dirname($curDir);
        // If we use top-level directory in Docker, then dirname will be '/' and result will be incorrect
        if ($parent === '/') {
            $parent = '';
        }

        return [
            ['/some/absolute/file' , '/some/absolute/file'],
            ['/some/absolute/file/../points/' , '/some/absolute/points/'],
            ['/some/./point.php' , '/some/point.php'],

            ['relative/to/the/dir' , "$curDir/relative/to/the/dir"],
            ['../relative/filename' , "$parent/relative/filename"],
            ['./point/file' , "$curDir/point/file"],

            ['C:\\Windows\\..\\filename', 'C:\\filename'],

            ['http://localhost/file.name' , 'http://localhost/file.name'],
            ['http://localhost/some/../relative.file' , 'http://localhost/relative.file'],

            ['phar://go.phar/some/path' , 'phar://go.phar/some/path'],
            ['phar://go.phar/some/../relative.file' , 'phar://go.phar/relative.file'],
        ];
    }
}
