<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Aspect;
use ReflectionClass;

/**
 * Cached loader is responsible for faster initialization of pointcuts/advisors for concrete aspect
 *
 * @property AspectLoader loader
 */
class CachedAspectLoader extends AspectLoader
{

    /**
     * Path to the cache directory
     *
     * @var null|string
     */
    protected $cacheDir;

    /**
     * Identifier of original loader
     *
     * @var string
     */
    protected $loaderId;

    /**
     * Cached loader constructor
     *
     * @param AspectContainer $container Instance of container
     * @param string $loaderId Original loader identifier
     * @param array $options List of kernel options
     */
    public function __construct(AspectContainer $container, $loaderId, array $options = array())
    {
        $this->cacheDir  = isset($options['cacheDir']) ? $options['cacheDir'] : null;
        $this->loaderId  = $loaderId;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function load(Aspect $aspect)
    {
        $refAspect = new ReflectionClass($aspect);
        $fileName  = $this->cacheDir . '/_aspect/' . sha1($refAspect->getName());

        // If cache is present and actual, then use it
        if (file_exists($fileName) && filemtime($fileName) >= filemtime($refAspect->getFileName())) {
            $this->loadFromCache($fileName);
            $this->loadedResources[] = $refAspect->getFileName();

            return;
        }

        $pointcutsBefore = $this->container->getByTag('pointcut');
        $advisorsBefore  = $this->container->getByTag('advisor');
        $this->loader->load($aspect);
        $pointcutsAfter = $this->container->getByTag('pointcut');
        $advisorsAfter  = $this->container->getByTag('advisor');

        $newPointcuts = array_diff_key($pointcutsAfter, $pointcutsBefore);
        $newAdvisors  = array_diff_key($advisorsAfter, $advisorsBefore);

        if ($this->cacheDir) {
            $content = serialize($newPointcuts + $newAdvisors);
            if (!is_dir(dirname($fileName))) {
                mkdir(dirname($fileName));
            }
            file_put_contents($fileName, $content);
        }

    }

    /**
     * {@inheritdoc}
     */
    public function registerLoaderExtension(AspectLoaderExtension $loader)
    {
        $this->loader->registerLoaderExtension($loader);
    }

    /**
     * {@inheritdoc}
     */
    public function loadAdvisorsAndPointcuts()
    {
        $this->loader->loadAdvisorsAndPointcuts();
    }

    /**
     * {@inheritdoc}
     */
    public function __get($name)
    {
        if ($name === 'loader') {
            $this->loader = $this->container->get($this->loaderId);

            return $this->loader;
        }
        throw new \RuntimeException("Not implemented");
    }


    /**
     * Loads pointcuts and advisors from the file
     *
     * @param string $fileName Name of the file with cache
     *
     * @throws \InvalidArgumentException
     */
    protected function loadFromCache($fileName)
    {
        $content = file_get_contents($fileName);
        $items   = unserialize($content);
        foreach ($items as $itemName => $value) {
            list($itemType, $itemName) = explode('.', $itemName, 2);
            switch ($itemType) {
                case 'pointcut':
                    $this->container->registerPointcut($value, $itemName);
                    break;

                case 'advisor':
                    $this->container->registerAdvisor($value, $itemName);
                    break;

                default:
                    throw new \InvalidArgumentException("Unknown item type {$itemType}");
            }
        }
    }
}
