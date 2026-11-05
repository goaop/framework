<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use Composer\Autoload\ClassLoader;
use Go\Instrument\PathResolver;
use Go\ParserReflection\LocatorInterface;

/**
 * Locates the original source of a class for goaop/parser-reflection, never a woven cache file
 *
 * In production the woven class map of the cache is injected into the Composer loader (see AopComposerLoader), so
 * the loader resolves a woven class to its cached file, which declares the trait of the original class and not the
 * class. The weaver reflects classes by name (ancestors, classes of property advices) and must read their sources:
 * every Composer loader is asked, a wrapped one by its own rules, without the injected class map, so that the
 * answer is the same in every weaving path, also while another cache directory is built.
 *
 * @internal Installed by AopComposerLoader::init(), not a public extension point
 */
final class OriginalSourceLocator implements LocatorInterface
{
    public function locateClass(string $className): false|string
    {
        $className = ltrim($className, '\\');
        foreach (spl_autoload_functions() as $loader) {
            if (!is_array($loader)) {
                continue;
            }
            $file = match (true) {
                $loader[0] instanceof AopComposerLoader => $loader[0]->findSourceFile($className),
                $loader[0] instanceof ClassLoader       => $loader[0]->findFile($className),
                default                                 => false,
            };
            if ($file !== false) {
                $resolvedPath = PathResolver::realpath($file);

                return is_string($resolvedPath) ? $resolvedPath : false;
            }
        }

        return false;
    }
}
