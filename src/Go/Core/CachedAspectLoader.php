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

use Doctrine\Common\Annotations\Reader;
use Go\Aop\Aspect;
use ReflectionClass;

/**
 * Cached loader is responsible for faster initialization of pointcuts/advisors for concrete aspect
 */
class CachedAspectLoader extends AspectLoader
{

    protected $cacheDir;

    /**
     * {@inheritdoc}
     * @param array $options
     */
    public function __construct(AspectContainer $container, Reader $reader, array $options = array())
    {
        parent::__construct($container, $reader);
        $this->cacheDir = $options['cacheDir'];
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
        parent::load($aspect);
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
