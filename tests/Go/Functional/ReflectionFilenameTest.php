<?php
declare(strict_types = 1);

namespace Go\Functional;

use Go\Core\AspectKernel;
use Go\ParserReflection\ReflectionClass;
use Go\Tests\TestProject\Application\Main;
use Go\Instrument\Transformer\FilterInjectorTransformer;

class ReflectionFilenameTest extends BaseFunctionalTest
{
    protected function warmUp(): void
    {
        $loader = $this->configuration['frontController'];
        $path = stream_resolve_include_path($loader);
        if (!is_readable($path)) {
            throw new \InvalidArgumentException("Invalid loader path: {$loader}");
        }

        ob_start();
        include_once $path;
        ob_end_clean();

        if (!class_exists(AspectKernel::class, false)) {
            $message = "Kernel was not initialized yet, please configure it in the {$path}";
            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $reflectedClass = new \ReflectionClass(FilterInjectorTransformer::class);
        $reflectedProperty = $reflectedClass->getProperty('kernel');
        $reflectedProperty->setAccessible(true);
        $reflectedProperty = $reflectedProperty->setValue(null);
    }

    public function testReflectionFilenameIsCorrect()
    {
        $filename = (new ReflectionClass(Main::class))->getFileName();
        $main = new Main();
        $this->assertEquals($filename, $main->getFilename());
    }
}
