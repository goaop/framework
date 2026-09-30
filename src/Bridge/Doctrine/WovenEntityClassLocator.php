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

namespace Go\Bridge\Doctrine;

use Doctrine\Persistence\Mapping\Driver\ClassLocator;
use Go\ParserReflection\ReflectionFile;
use InvalidArgumentException;
use PhpParser\Node\Stmt\Class_;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Discovers entity classes for Doctrine mapping drivers without including their source files.
 *
 * Doctrine's default FileClassLocator (used when a mapping driver is given plain directories)
 * `require_once`s every source file and keeps the classes declared by those files. With Go! AOP
 * active this breaks woven entities in two ways:
 *  - an entity that is not loaded yet is declared from its original source, bypassing the weaver,
 *    so its aspects are silently lost for the rest of the request;
 *  - an entity that is already loaded was declared by its woven proxy file in the AOP cache, so
 *    it is not attributed to the scanned file and is missing from getAllClassNames(), and thus
 *    from the schema tool, migrations and `orm:validate-schema`.
 *
 * This locator reads class names from the source files with a parser instead, and loads each
 * class through the autoloader, which serves the woven version:
 *
 *     $driver = new AttributeDriver(WovenEntityClassLocator::createFromDirectories([__DIR__ . '/src/Entity']));
 */
final readonly class WovenEntityClassLocator implements ClassLocator
{
    /**
     * @param iterable<SplFileInfo|string> $files Source files to scan
     */
    public function __construct(private iterable $files) {}

    /**
     * Creates a locator for all files with the given extension below the directories
     *
     * @param list<string> $directories         Directories to scan recursively
     * @param list<string> $excludedDirectories Directories to skip, relative to the scanned ones or absolute
     */
    public static function createFromDirectories(
        array $directories,
        array $excludedDirectories = [],
        string $fileExtension = '.php',
    ): self {
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                throw new InvalidArgumentException(sprintf('Entity directory "%s" does not exist.', $directory));
            }
        }
        $finder = new Finder()
            ->files()
            ->in($directories)
            ->name('*' . $fileExtension)
            ->sortByName();

        $absoluteExclusions = [];
        foreach ($excludedDirectories as $excludedDirectory) {
            $realPath = realpath($excludedDirectory);
            if ($realPath !== false && is_dir($realPath)) {
                $absoluteExclusions[] = rtrim(str_replace('\\', '/', $realPath), '/') . '/';
            } else {
                $finder->exclude($excludedDirectory);
            }
        }
        if ($absoluteExclusions !== []) {
            $finder->filter(static function (SplFileInfo $file) use ($absoluteExclusions): bool {
                $path = str_replace('\\', '/', $file->getPathname());
                foreach ($absoluteExclusions as $exclusion) {
                    if (str_starts_with($path, $exclusion)) {
                        return false;
                    }
                }

                return true;
            });
        }

        return new self($finder);
    }

    /**
     * @return list<class-string>
     */
    public function getClassNames(): array
    {
        $classNames = [];
        foreach ($this->files as $file) {
            $fileName = $file instanceof SplFileInfo ? $file->getPathname() : $file;
            if (!is_file($fileName)) {
                continue;
            }
            foreach (new ReflectionFile($fileName)->getFileNamespaces() as $namespace) {
                foreach ($namespace->getClasses() as $className => $class) {
                    // Interfaces, traits and enums are never entities
                    if (!$class->getNode() instanceof Class_) {
                        continue;
                    }
                    // Loading through the autoloader is what yields the woven class
                    if (class_exists($className)) {
                        $classNames[$className] = $className;
                    }
                }
            }
        }

        return array_values($classNames);
    }
}
