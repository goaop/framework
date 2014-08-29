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
     * List of internal dependencies that should not be analyzed by AOP
     *
     * @var array
     */
    protected $internalNamespaces = array(
        'Go\\',
        'Dissect\\',
        'Doctrine\\Common\\',
        'TokenReflection\\',
    );

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
     * Initialize aspect autoloader
     *
     * Replaces original composer autoloader with wrapper
     */
    public static function init()
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
                $loader[0] = new AopComposerLoader($loader[0]);
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
            $isInternal = false;
            foreach ($this->internalNamespaces as $ns) {
                if (strpos($class, $ns) === 0) {
                    $isInternal = true;
                    break;
                }
            }

            include ($isInternal ? $file : FilterInjectorTransformer::rewrite($file));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findFile($class)
    {
        return $this->original->findFile($class);
    }
}
