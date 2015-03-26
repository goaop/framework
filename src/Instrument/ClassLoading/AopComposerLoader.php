<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

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
    protected $options = array();

    /**
     * Constructs an wrapper for the composer loader
     *
     * @param ClassLoader $original Instance of current loader
     */
    public function __construct(ClassLoader $original, array $options = array())
    {
        $this->options  = $options;
        $this->original = $original;
    }

    /**
     * Initialize aspect autoloader
     *
     * Replaces original composer autoloader with wrapper
     *
     * @param array $options Aspect kernel options
     */
    public static function init(array $options = array())
    {
        $loaders = spl_autoload_functions();

        foreach ($loaders as &$loader) {
            $loaderToUnregister = $loader;
            if (is_array($loader) && ($loader[0] instanceof ClassLoader)) {
                $originalLoader = $loader[0];

                // Configure library loader for doctrine annotation loader
                AnnotationRegistry::registerLoader(function ($class) use ($originalLoader) {
                    $originalLoader->loadClass($class);

                    return class_exists($class, false);
                });
                $loader[0] = new AopComposerLoader($loader[0], $options);
            }
            spl_autoload_unregister($loaderToUnregister);
        }
        unset($loader);

        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }
    }

    /**
     * Autoload a class by it's name
     */
    public function loadClass($class)
    {
        if ($file = $this->original->findFile($class)) {
            $isAllowedToTransform = $this->isAllowedToTransform($file);

            if ($isAllowedToTransform) {
                include FilterInjectorTransformer::rewrite($file);
            } else {
                include $file;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findFile($class)
    {
        return $this->original->findFile($class);
    }

    /**
     * Verifies if file should be transformed or not
     *
     * @param string $fileName Name of the file to transform
     * @return bool
     */
    private function isAllowedToTransform($fileName)
    {
        static $appDir, $includePaths, $excludePaths;
        if (!isset($appDir, $includePaths, $excludePaths)) {
            extract($this->options, EXTR_IF_EXISTS);
            $excludePaths[] = substr(__DIR__, 0, /* strlen('/Instrument/ClassLoading') */ -24);
        }

        // Do not touch files that not under appDir
        if (strpos($fileName, $appDir) !== 0) {
            return false;
        }

        if ($includePaths) {
            $found = false;
            foreach ($includePaths as $includePath) {
                if (strpos($fileName, $includePath) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        foreach ($excludePaths as $excludePath) {
            if (strpos($fileName, $excludePath) === 0) {
                return false;
            }
        }

        return true;
    }
}
