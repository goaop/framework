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
use PHPUnit\Framework\TestCase;

/**
 * The PHPUnit process itself runs on the Composer autoloader of this repository
 */
class ComposerAutoloaderTest extends TestCase
{
    public function testLocatesTheAutoloaderOfTheProcess(): void
    {
        $autoloader = ComposerAutoloader::ofProcess();

        $this->assertSame(realpath(__DIR__ . '/../../../vendor'), realpath($autoloader->vendorDir));
        $this->assertFileExists($autoloader->autoloadFile);
        $this->assertContains($autoloader->classLoader, ClassLoader::getRegisteredLoaders());
    }

    public function testListsTheFilesComposerIncludesOnEveryRequest(): void
    {
        $files = ComposerAutoloader::ofProcess()->autoloadedFiles();

        $this->assertNotEmpty($files, 'The dependencies of this repository have autoloaded files');
        foreach ($files as $file) {
            $this->assertFileExists($file);
        }
    }
}
