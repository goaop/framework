<?php
declare(strict_types = 1);
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
 * AopComposerLoader class is responsible to use a weaver for classes instead of original one
 */
class AopComposerLoader
{
    /**
     * List of packages to exclude from analysis
     *
     * @var array
     */
    public static $excludedPackages = [
        'Dissect',
        'Doctrine\\Common\Lexer\\',
        'Doctrine\\Common\\Annotations\\',
        'Go\\',
        'Go\\ParserReflection\\',
        'PhpParser\\'
    ];

    /**
     * Instance of original autoloader
     *
     * @var ClassLoader
     */
    protected $original;

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
        $this->options  = $options;
        $this->original = $original;

        $prefixes     = $original->getPrefixes() + $original->getPrefixesPsr4();
        $excludePaths = $options['excludePaths'];

        foreach (self::$excludedPackages as $packageName) {
            if (isset($prefixes[$packageName])) {
                $excludePaths[] = PathResolver::realpath($prefixes[$packageName][0]);
            }
        }

        $this->fileEnumerator = new Enumerator($options['appDir'], $options['includePaths'], $excludePaths);
        $this->cacheState     = $container->get('aspect.cache.path.manager')->queryCacheState();
    }

    /**
     * Initialize aspect autoloader
     *
     * Replaces original composer autoloader with wrapper
     *
     * @param array $options Aspect kernel options
     * @param AspectContainer $container
     */
    public static function init(array $options, AspectContainer $container)
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
                $loader[0] = new AopComposerLoader($loader[0], $container, $options);
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
     *
     * @param string $class Name of the class to load
     */
    public function loadClass(string $class)
    {
        $file = $this->findFile($class);

        if ($file !== false) {
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
    public function findFile(string $class)
    {
        static $isAllowedFilter = null, $isProduction = false;
        if (!$isAllowedFilter) {
            $isAllowedFilter = $this->fileEnumerator->getFilter();
            $isProduction    = !$this->options['debug'];
        }

        $file = $this->original->findFile($class);

        if ($file) {

            /**
             * Composer can return relative paths for >=5.6
             * @see https://github.com/composer/composer/pull/5174
             */
            if (strpos($file, '..') !== false) {
                $file = PathResolver::realpath($file);
            }
            $cacheState = isset($this->cacheState[$file]) ? $this->cacheState[$file] : null;
            if ($cacheState && $isProduction) {
                $file = $cacheState['cacheUri'] ?: $file;
            } elseif ($isAllowedFilter(new \SplFileInfo($file))) {
                // can be optimized here with $cacheState even for debug mode, but no needed right now
                $file = FilterInjectorTransformer::rewrite($file);
            }
        }

        return $file;
    }
}
