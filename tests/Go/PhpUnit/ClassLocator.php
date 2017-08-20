<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\PhpUnit;

use Go\Core\AspectContainer;
use Go\Instrument\PathResolver;
use Go\ParserReflection\LocatorInterface;
use Go\ParserReflection\ReflectionEngine;
use ReflectionClass;
use ReflectionProperty;

final class ClassLocator implements LocatorInterface
{
    /**
     * @var LocatorInterface
     */
    private $originalLocator;

    /**
     * @var array
     */
    private $configuration;

    public function __construct(LocatorInterface $original, array $configuration)
    {
        $this->originalLocator = $original;
        $this->configuration   = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function locateClass($className)
    {
        $isAopProxied = substr($className, -strlen(AspectContainer::AOP_PROXIED_SUFFIX)) === AspectContainer::AOP_PROXIED_SUFFIX;

        if ($isAopProxied) {
            try {
                $path = $this->locateProxiedClass($className);

                if (file_exists($path)) {
                    return $path;
                }
            } catch (\Exception $e) {
                /* noop, continue */
            }
        }

        try {
            $path = $this->locateWovenClass($className);

            if (file_exists($path)) {
                return $path;
            }
        } catch (\Exception $e) {
            /* noop, continue */
        }

        return $this->originalLocator->locateClass($className);
    }

    /**
     * Get original locator.
     *
     * @return LocatorInterface
     */
    public function getOriginalLocator()
    {
        return $this->originalLocator;
    }

    /**
     * Initialize this locator into Go\ParserReflection\ReflectionEngine
     *
     * @see \Go\ParserReflection\ReflectionEngine::init()
     *
     * @param array $configuration
     */
    public static function initialize(array $configuration)
    {
        $originalLocatorProperty = new ReflectionProperty(ReflectionEngine::class, 'locator');
        $originalLocatorProperty->setAccessible(true);

        $originalLocator = $originalLocatorProperty->getValue(null);
        $locator         = new ClassLocator($originalLocator, $configuration);

        ReflectionEngine::init($locator);
    }

    /**
     * Remove this locator and restore previous one.
     */
    public static function restore()
    {
        $locatorProperty = new ReflectionProperty(ReflectionEngine::class, 'locator');
        $locatorProperty->setAccessible(true);

        $locator = $locatorProperty->getValue(null);

        if (!$locator instanceof ClassLocator) {
            return;
        }

        ReflectionEngine::init($locator->getOriginalLocator());
    }

    /**
     * Locate AOP woven class from functional thread context.
     *
     * @param string $className Class to locate.
     * @return string Path to file where class is stored.
     */
    private function locateWovenClass($className)
    {
        $filename = (new ReflectionClass($className))->getFileName();
        $appDir   = PathResolver::realpath($this->configuration['appDir']);
        $suffix   = substr($filename, strlen($appDir));
        $woven    = PathResolver::realpath($this->configuration['cacheDir'] . '/_proxies' . $suffix);

        return $woven;
    }

    /**
     * Locate AOP proxied class from functional thread context.
     *
     * @param string $className Class to locate.
     * @return string Path to file where class is stored.
     */
    private function locateProxiedClass($className)
    {
        $originalClass = substr($className, 0, -strlen(AspectContainer::AOP_PROXIED_SUFFIX));
        $filename      = (new ReflectionClass($originalClass))->getFileName();
        $appDir        = PathResolver::realpath($this->configuration['appDir']);
        $suffix        = substr($filename, strlen($appDir));
        $proxied       = PathResolver::realpath($this->configuration['cacheDir'] . $suffix);

        return $proxied;
    }
}
