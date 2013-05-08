<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\ClassLoading;

use Go\Instrument\Transformer\FilterInjectorTransformer;

use Composer\Autoload\ClassLoader;

/**
 * AopComposerLoader class is responsible to use a weaver for classes instead of original one
 *
 * @package Go\Instrument\ClassLoading
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
     * Constructs an wrapper for the composer loader
     *
     * @param ClassLoader $original Instance of current loader
     */
    public function __construct(ClassLoader $original)
    {
        $this->original = $original;
    }

    /**
     * Replaces original composer autoloader with wrapper
     */
    public static function replaceOriginal()
    {
        $loaders = spl_autoload_functions();

        $newLoaders = array();
        foreach ($loaders as $loader) {
            spl_autoload_unregister($loader);
            if (is_array($loader) && ($loader[0] instanceof ClassLoader)) {
                $loader[0] = new AopComposerLoader($loader[0]);
            }
            array_push($newLoaders, $loader);
        }
        foreach ($newLoaders as $loader) {
            spl_autoload_register($loader);
        }
    }

    /**
     * Autoload a class by it's name
     */
    public function loadClass($class)
    {
        if ($file = $this->original->findFile($class)) {
            include ($this->isInternal($class) ? $file : FilterInjectorTransformer::rewrite($file));
        }
    }

    /**
     * Checks if a class is internal for Go! framework
     *
     * @param string $class Name of the class to load
     *
     * @return bool
     */
    protected function isInternal($class)
    {
        foreach (UniversalClassLoader::$internalNamespaces as $ns) {
            if (strpos($class, $ns) === 0) {
                return true;
            }
        }
        return false;
    }
}

AopComposerLoader::replaceOriginal();