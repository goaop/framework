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
use Doctrine\Common\Annotations\AnnotationRegistry;

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
     * List of internal dependencies that should not be analyzed by AOP
     *
     * @var array
     */
    protected $internalNamespaces = array(
        'Go',
        'Dissect',
        'Doctrine\\Common',
        'TokenReflection',
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
            if (is_array($loader) && ($loader[0] instanceof ClassLoader)) {
                $originalLoader = $loader[0];

                // Configure library loader for doctrine annotation loader
                AnnotationRegistry::registerLoader(function($class) use ($originalLoader) {
                    $originalLoader->loadClass($class);
                    return class_exists($class, false);
                });
                spl_autoload_unregister($loader);
                $loader[0] = new AopComposerLoader($loader[0]);
            }
        }
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
}

AopComposerLoader::init();