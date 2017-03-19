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
     * Was initialization successful or not
     *
     * @var bool
     */
    private static $wasInitialized = false;

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

        $prefixes     = $original->getPrefixes();
        $excludePaths = $options['excludePaths'];

        if (!empty($prefixes)) {
            // Let's exclude core dependencies from that list
            if (isset($prefixes['Dissect'])) {
                $excludePaths[] = $prefixes['Dissect'][0];
            }
            if (isset($prefixes['Doctrine\\Common\\Annotations\\'])) {
                $excludePaths[] = substr($prefixes['Doctrine\\Common\\Annotations\\'][0], 0, -16);
            }
        }

        $fileEnumerator       = new Enumerator($options['appDir'], $options['includePaths'], $excludePaths);
        $this->fileEnumerator = $fileEnumerator;
        $this->cacheState     = $container->get('aspect.cache.path.manager')->queryCacheState();
    }

    /**
     * Initialize aspect autoloader
     *
     * Replaces original composer autoloader with wrapper
     *
     * @param array $options Aspect kernel options
     * @param AspectContainer $container
     *
     * @return bool was initialization sucessful or not
     */
    public static function init(array $options = [], AspectContainer $container)
    {
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
                self::$wasInitialized = true;
            }
            spl_autoload_unregister($loaderToUnregister);
        }
        unset($loader);

        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }

        return self::$wasInitialized;
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
        static $isAllowedFilter = null, $isProduction = false;
        if (!$isAllowedFilter) {
            $isAllowedFilter = $this->fileEnumerator->getFilter();
            $isProduction    = !$this->options['debug'];
        }

        $file = $this->original->findFile($class);

        if ($file) {
            $file = PathResolver::realpath($file)?:$file;
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

    /**
     * Whether or not loader was initialized
     *
     * @return bool
     */
    public static function wasInitialized()
    {
        return self::$wasInitialized;
    }
}
