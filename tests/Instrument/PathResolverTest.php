<?php

declare(strict_types=1);
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

    public function testArrayResolutionChecksExistenceOfEveryPath(): void
    {
        $this->assertSame([__DIR__, false], PathResolver::realpath([__DIR__, __DIR__ . '/bad/dir'], true));
    }

    public function testOneCharacterRelativePathIsResolvedAgainstWorkingDirectory(): void
    {
        $this->assertSame(getcwd() . DIRECTORY_SEPARATOR . 'z', PathResolver::realpath('z'));
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

        assert($localPath !== null);
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
     * @return array<array{string, string}>
     */
    public static function realpathExamples(): array
    {
        $curDir = getcwd();
        assert($curDir !== false);
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
            ['C:\\..\\filename', 'C:\\filename'],
            ['/../outside/file', '/outside/file'],
            ['/some//doubled///separators/', '/some/doubled/separators/'],

            ['http://localhost/file.name' , 'http://localhost/file.name'],
            ['http://localhost/some/../relative.file' , 'http://localhost/relative.file'],

            ['phar://go.phar/some/path' , 'phar://go.phar/some/path'],
            ['phar://go.phar/some/../relative.file' , 'phar://go.phar/relative.file'],
        ];
    }

    /**
     * @return array<string, array{string, string, string, string|null}>
     */
    public static function rebaseExamples(): array
    {
        return [
            'file below directory'            => ['/app/src/Foo.php', '/app', '/cache', '/cache/src/Foo.php'],
            'trailing separator on directory' => ['/app/src/Foo.php', '/app/', '/cache/', '/cache/src/Foo.php'],
            'directory itself'                => ['/app', '/app', '/cache', '/cache'],
            'nested repeat of directory name' => ['/app/src/app/Foo.php', '/app', '/cache', '/cache/src/app/Foo.php'],
            'sibling sharing a name prefix'   => ['/var/www-old/Foo.php', '/var/www', '/cache', null],
            'directory in the middle'         => ['/srv/app/Foo.php', '/app', '/cache', null],
            'empty directory'                 => ['/app/Foo.php', '', '/cache', null],
            'file-system root'                => ['/app/Foo.php', '/', '/cache', '/cache/app/Foo.php'],
            'windows separators'              => ['C:\\app\\Foo.php', 'C:\\app', 'C:\\cache', 'C:\\cache\\Foo.php'],
            'empty target'                    => ['/app/src/Foo.php', '/app', '', '/src/Foo.php'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rebaseExamples')]
    public function testRebaseReplacesOnlyTheLeadingDirectory(
        string $path,
        string $fromDirectory,
        string $toDirectory,
        ?string $expected,
    ): void {
        $this->assertSame($expected, PathResolver::rebase($path, $fromDirectory, $toDirectory));
        $this->assertSame($expected !== null, PathResolver::isBelow($path, $fromDirectory));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function suffixExamples(): array
    {
        return [
            'plain file'                => ['/cache/src/Foo.php', '/cache/src/FooOriginalTrait.php'],
            'directory named like file' => ['/cache/lib.php/Foo.php', '/cache/lib.php/FooOriginalTrait.php'],
            '.php inside the file name' => ['/cache/Foo.phpBar.php', '/cache/Foo.phpBarOriginalTrait.php'],
            'no extension'              => ['/cache/Foo', '/cache/FooOriginalTrait'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('suffixExamples')]
    public function testWithSuffixBeforeExtensionTouchesOnlyTheExtension(string $path, string $expected): void
    {
        $this->assertSame($expected, PathResolver::withSuffixBeforeExtension($path, 'OriginalTrait'));
    }
}
