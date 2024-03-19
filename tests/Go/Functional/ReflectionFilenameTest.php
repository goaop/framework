<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Functional;

use Go\Core\AspectKernel;
use Go\ParserReflection\ReflectionClass;
use Go\Tests\TestProject\Application\Main;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use InvalidArgumentException;

class ReflectionFilenameTest extends BaseFunctionalTestCase
{
    protected function warmUp(): void
    {
        $loader = $this->configuration['frontController'];
        $path   = stream_resolve_include_path($loader);
        if (!is_readable($path)) {
            throw new InvalidArgumentException("Invalid loader path: {$loader}");
        }

        ob_start();
        include_once $path;
        ob_end_clean();

        if (!class_exists(AspectKernel::class, false)) {
            $message = "Kernel was not initialized yet, please configure it in the {$path}";
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $reflectedClass    = new ReflectionClass(FilterInjectorTransformer::class);
        $reflectedProperty = $reflectedClass->getProperty('kernel');
        $reflectedProperty->setAccessible(true);
        $reflectedProperty->setValue(null);
    }

    public function testReflectionFilenameIsCorrect()
    {
        $filename = (new ReflectionClass(Main::class))->getFileName();
        $main     = new Main();
        $this->assertEquals($filename, $main->getFilename());
    }
}
