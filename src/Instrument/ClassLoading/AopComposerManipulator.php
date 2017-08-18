<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use Go\Aop\AspectException;
use Go\Core\AspectContainer;
use Go\Instrument\FileSystem\Enumerator;
use Go\Instrument\PathResolver;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Composer\Autoload\ClassLoader;
use Doctrine\Common\Annotations\AnnotationRegistry;

/**
 * AopComposerManipulator class adjusts composer to work with AOP-weaving mechanism
 */
class AopComposerManipulator
{
    /**
     * List of packages to exclude from analysis
     *
     * @var array
     */
    public static $excludedPackages = [
        'Dissect'                         => true,
        'Doctrine\\Common\Lexer\\'        => true,
        'Doctrine\\Common\\Annotations\\' => true,
        'Go\\'                            => true,
        'Go\\ParserReflection\\'          => true,
        'PhpParser\\'                     => true
    ];

    /**
     * Instance of original autoloader
     *
     * @var ClassLoader
     */
    protected $original = null;

    /**
     * AOP kernel options
     *
     * @var array
     */
    protected $options = [];

    /**
     * File enumerator
     *
     * @var Enumerator
     */
    protected $fileEnumerator;

    /**
     * Cache state
     *
     * @var array
     */
    private $cacheState;

    /**
     * Constructs an wrapper for the composer loader
     *
     * @param ClassLoader $original Instance of current loader
     * @param AspectContainer $container Instance of the container
     * @param array $options Configuration options
     */
    public function __construct(ClassLoader $original, AspectContainer $container, array $options = [])
    {
        $this->options    = $options;
        $this->original   = $original;
        $this->cacheState = &$container->get('aspect.cache.path.manager')->queryClassMap();

        $this->adjustClassLoader();
    }

    /**
     * Initialize aspect autoloader
     *
     * Replaces original composer autoloader with wrapper
     *
     * @param array $options Aspect kernel options
     * @param AspectContainer $container
     */
    public static function init(array $options = [], AspectContainer $container)
    {
        $wasInitialized = false;
        $loaders = spl_autoload_functions();

        foreach ($loaders as &$loader) {
            $loaderToUnregister = $loader;
            if (is_array($loader) && ($loader[0] instanceof ClassLoader)) {
                $originalLoader = $loader[0];
                // Configure library loader for doctrine annotation loader
                AnnotationRegistry::registerLoader(function($class) use ($originalLoader) {
                    $originalLoader->loadClass($class);

                    return class_exists($class, false);
                });
                $loader[0]      = new AopComposerManipulator($loader[0], $container, $options);
                $wasInitialized = true;
            }
            spl_autoload_unregister($loaderToUnregister);
        }
        unset($loader);

        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }

        if (!$wasInitialized) {
            throw new AspectException("Initialization of aspect loader failed, check your composer initialization");
        };
    }

    /**
     * Autoload a class by it's name
     */
    public function loadClass($class)
    {
        $file = $this->findFile($class);

        if ($file) {
            include $file;
        }
    }

    /**
     * Finds either the path to the file where the class is defined,
     * or gets the appropriate php://filter stream for the given class.
     *
     * @param string $class
     * @return string|false The path/resource if found, false otherwise.
     */
    public function findFile($class)
    {
        $file = $this->original->findFile($class);

        // Our special marker for intercepted files
        if (strpos($file, 'file://') === 0) {
            // cut first symbols 'file://' from the path and rewrite it
            $file = substr($file, 7);
            // last check to be sure, that there aren't any internal dirs or files in exclude list
            if (!$this->underPath($file, $this->options['excludePaths'])) {
                $file = FilterInjectorTransformer::rewrite($file);
            }
        }

        return $file;
    }

    /**
     * Adjusts original composer loader to work together with AOP engine
     */
    private function adjustClassLoader()
    {
        // PSR-0 prefixes analysis
        $prefixes = $this->original->getPrefixes();
        foreach ($prefixes as $prefix => $prefixPaths) {
            // Ignore core packages
            if (isset(static::$excludedPackages[$prefix])) {
                continue;
            }
            $adjustedPrefixes = $this->analysePrefixPaths($prefixPaths);
            $this->original->set($prefix, $adjustedPrefixes);
        }
        // PSR-4 prefixes analysis
        $prefixesPsr4 = $this->original->getPrefixesPsr4();
        foreach ($prefixesPsr4 as $prefix => $prefixPaths) {
            // Ignore core packages
            if (isset(static::$excludedPackages[$prefix])) {
                continue;
            }
            $adjustedPrefixes = $this->analysePrefixPaths($prefixPaths);
            $this->original->setPsr4($prefix, $adjustedPrefixes);
        }
        $this->original->addClassMap($this->cacheState);
    }

    /**
     * Checks if the path belongs to the specific directory
     *
     * @param string $absolutePath Absolute path to check (should be normalized)
     * @param array  $listOfPaths  List of absolute paths to check against
     *
     * @return bool True, if given path belongs to the list of directories
     */
    private function underPath($absolutePath, array $listOfPaths)
    {
        foreach ($listOfPaths as $singlePath) {
            if (strpos($absolutePath, $singlePath) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Perform analysis of prefix paths
     *
     * @param array  $prefixPaths List of prefix paths
     *
     * @return array List of normalized/transformed paths
     */
    private function analysePrefixPaths(array $prefixPaths)
    {
        $adjustedPrefixes = [];
        foreach ($prefixPaths as $prefixPath) {
            $normalizedPath = PathResolver::realpath($prefixPath);
            $isUnderRoot    = $this->underPath($normalizedPath, (array)$this->options['appDir']);
            $hasIncluded    = !empty($this->options['includePaths']);
            $isIncluded     = $hasIncluded && $this->underPath($normalizedPath, $this->options['includePaths']);
            $isExcluded     = $this->underPath($normalizedPath, $this->options['excludePaths']);
            $canProcess     = $isUnderRoot && ($hasIncluded ? $isIncluded : true) && !$isExcluded;
            if ($canProcess) {
                // Trick to distinguish between intercepted files without affecting file checking logic
                $normalizedPath = 'file://' . $normalizedPath;
            }

            $adjustedPrefixes[] = $normalizedPath;
        }

        return $adjustedPrefixes;
    }
}
