<?php
declare(strict_types = 1);

include_once __DIR__ . '/../../../../vendor/autoload.php';

// MultiClassHolder.php declares several classes (issue #760): the woven MultiClassSecond extends MultiClassPlain of
// that file. Weaving reflects the parent through composer, so it is registered the way a classmap autoloaded
// project knows it (PSR-4 can not locate a class declared in a file named after another class)
foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $composerLoader) {
    $composerLoader->addClassMap([
        \Go\Tests\TestProject\Application\MultiClassPlain::class => __DIR__ . '/../src/Application/MultiClassHolder.php',
    ]);
}

$configuration = ($env = getenv('GO_AOP_CONFIGURATION')) ? $env : 'default' ;
$settings = require __DIR__.'/configuration.php';

$applicationAspectKernel = $settings[$configuration]['kernel']::getInstance();
// The test-harness keys (kernel class, console and front-controller paths) are not kernel options
$applicationAspectKernel->init(array_diff_key($settings[$configuration], array_flip(['kernel', 'console', 'frontController'])));
