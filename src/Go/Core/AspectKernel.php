<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

use Go\Instrument\ClassLoading\UniversalClassLoader;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\WeavingTransformer;
use Go\Instrument\Transformer\CachingTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\MagicConstantTransformer;

use Doctrine\Common\Annotations\AnnotationRegistry;

use TokenReflection;

/**
 * Whether or not we have a modern PHP
 */
define('IS_MODERN_PHP', version_compare(PHP_VERSION, '5.4.0') >= 0);


/**
 * Abstract aspect kernel is used to prepare an application to work with aspects.
 *
 * Realization of this class should return the path for application loader, so when the kernel has finished its work,
 * it will pass the control to the application loader.
 */
abstract class AspectKernel
{
    /**
     * Kernel options
     *
     * @var array
     */
    protected $options = array();

    /**
     * Single instance of kernel
     *
     * @var null|static
     */
    protected static $instance = null;

    /**
     * Default class name for container, can be redefined in children
     *
     * @var string
     */
    protected static $containerClass = 'Go\Core\AspectContainer';

    /**
     * Aspect container instance
     *
     * @var null|AspectContainer
     */
    protected $container = null;

    /**
     * Protected constructor is used to prevent direct creation, but allows customization if needed
     */
    protected function __construct() {}

    /**
     * Returns the single instance of kernel
     *
     * @return AspectKernel
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Init the kernel and make adjustments
     *
     * @param array $options Associative array of options for kernel
     */
    public function init(array $options = array())
    {
        $this->options = array_replace_recursive($this->getDefaultOptions(), $options);
        $this->initLibraryLoader();

        /** @var $container AspectContainer */
        $container = $this->container = new $this->options['containerClass'];
        $container->set('kernel', $this);

        $sourceLoaderFilter = new SourceTransformingLoader();
        $sourceLoaderFilter->register();

        foreach ($this->registerTransformers($sourceLoaderFilter) as $sourceTransformer) {
            $sourceLoaderFilter->addTransformer($sourceTransformer);
        }

        // Load application configurator
        $sourceLoaderFilter->load($this->options['appLoader']);

        // Register kernel resources in the container
        $this->addKernelResourcesToContainer($container);

        // Register all AOP configuration in the container
        $this->configureAop($container);
    }

    /**
     * Returns an aspect container
     *
     * @return null|AspectContainer
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Returns list of kernel options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Returns default options for kernel
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        $libraryComposer     = __DIR__ . '/../../../vendor/composer/autoload_namespaces.php';
        $applicationComposer = __DIR__ . '/../../../../../vendor/composer/autoload_namespaces.php';

        switch (true) {
            case file_exists($libraryComposer):
                $composer = $libraryComposer;
                break;

            case file_exists($applicationComposer):
                $composer = $applicationComposer;
                break;

            default:
                $composer = null;
        }

        return array(
            //Debug mode
            'debug'     => false,
            // Base application directory
            'appDir'    => __DIR__ . '/../../../../../../',
            // Cache directory for Go! generated classes
            'cacheDir'  => null,
            // Path to the application autoloader file, typical autoload.php
            'appLoader' => null,

            // Configuration for autoload namespaces, can use composer
            'autoloadPaths'  => $composer ? (include $composer) : array(),
            // Include paths for aspect weaving
            'includePaths'   => array(),
            // Name of the class for container
            'containerClass' => static::$containerClass,
        );
    }

    /**
     * Configure an AspectContainer with advisors, aspects and pointcuts
     *
     * @param AspectContainer $container
     *
     * @return void
     */
    abstract protected function configureAop(AspectContainer $container);

    /**
     * Returns list of source transformers, that will be applied to the PHP source
     *
     * @param SourceTransformingLoader $sourceLoader Instance of source loader for information
     *
     * @return array|SourceTransformer[]
     */
    protected function registerTransformers(SourceTransformingLoader $sourceLoader)
    {
        $sourceTransformers = array(
            new FilterInjectorTransformer($this->options, $sourceLoader->getId()),
            new MagicConstantTransformer($this),
            new WeavingTransformer(
                $this,
                new TokenReflection\Broker(
                    new TokenReflection\Broker\Backend\Memory()
                )
            )
        );

        return array(
            new CachingTransformer($this, $sourceTransformers)
        );
    }

    /**
     * Init library autoloader.
     *
     * We cannot use any standard autoloaders in the application level because we will rewrite them on fly.
     * This will also reduce the time for library loading and prevent cyclic errors when source is loaded.
     */
    protected function initLibraryLoader()
    {

        require_once __DIR__ . '/../Instrument/ClassLoading/UniversalClassLoader.php';

        $loader = new UniversalClassLoader();
        if (empty($this->options['autoloadPaths'])) {
            throw new \InvalidArgumentException("Composer was not found, please configure an `autoloadPaths`");
        }
        $loader->registerNamespaces($this->options['autoloadPaths']);
        $loader->register();

        // Configure library loader for doctrine annotation loader
        AnnotationRegistry::registerLoader(function($class) use ($loader) {
            $loader->loadClass($class);
            return class_exists($class, false);
        });

    }

    /**
     * Add resources for kernel
     *
     * @param AspectContainer $container
     */
    protected function addKernelResourcesToContainer(AspectContainer $container)
    {
        $trace    = debug_backtrace();
        $refClass = new \ReflectionObject($this);

        $container->addResource($trace[1]['file']);
        $container->addResource($refClass->getFileName());
    }
}
