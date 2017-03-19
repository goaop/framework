<?php
declare(strict_types = 1);

namespace Go\Instrument;

class PathResolverTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Test existence checking
     */
    public function testCanResolveAndCheckExistence()
    {
        $this->assertEquals(__DIR__, PathResolver::realpath(__DIR__, true));
        $this->assertEquals(false, PathResolver::realpath(__DIR__ . '/bad/dir', true));
    }

    /**
     * Test multiple resolve
     */
    public function testCanResolveArray()
    {
        $this->assertEquals(array(__DIR__ , __FILE__), PathResolver::realpath(array(__DIR__ , __FILE__)));
    }

    /**
     * Test for checking the logic of custom realpath() implementation
     *
     * @param string $path Given path
     * @param string $expected Expected path
     *
     * @dataProvider realpathExamples
     */
    public function testRealpathWorkingCorrectly($path, $expected)
    {
        // Trick to get scheme name and path in one action. If no scheme, then there will be only one part
        $components = explode('://', $expected, 2);
        list ($pathScheme, $localPath) = isset($components[1]) ? $components : array(null, $components[0]);

        // resolve path parts (single dot, double dot and double delimiters)
        $localPath  = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $localPath);
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
    public function realpathExamples()
    {
        $curDir = getcwd();
        $parent = dirname($curDir);

        return array(
            array('/some/absolute/file' , '/some/absolute/file'),
            array('/some/absolute/file/../points/' , '/some/absolute/points/'),
            array('/some/./point.php' , '/some/point.php'),

            array('relative/to/the/dir' , "$curDir/relative/to/the/dir"),
            array('../relative/filename' , "$parent/relative/filename"),
            array('./point/file' , "$curDir/point/file"),

            array('C:\\Windows\\..\\filename', 'C:\\filename'),

            array('http://localhost/file.name' , 'http://localhost/file.name'),
            array('http://localhost/some/../relative.file' , 'http://localhost/relative.file'),

            array('phar://go.phar/some/path' , 'phar://go.phar/some/path'),
            array('phar://go.phar/some/../relative.file' , 'phar://go.phar/relative.file'),
        );
    }
}
