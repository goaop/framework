<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

use Go\Instrument\ClassLoading\UniversalClassLoader;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\Transformer\AopProxyTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;

/**
 * Separate class loader for core should be used to load classes,
 * so UniversalClassLoader is moved to the custom namespace
 */
include '../src/Go/Instrument/ClassLoading/UniversalClassLoader.php';

$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
    'Go'              => __DIR__ . '/../src/',
    'TokenReflection' => __DIR__ . '/../vendor/andrewsville/php-token-reflection/'
));
$loader->register();

SourceTransformingLoader::registerFilter();

$sourceTransformers = array(
    new FilterInjectorTransformer(__DIR__, __DIR__, SourceTransformingLoader::getId()),
    new AopProxyTransformer(
        new TokenReflection\Broker(
            new TokenReflection\Broker\Backend\Memory()
        )
    ),
);

foreach ($sourceTransformers as $sourceTransformer) {
    SourceTransformingLoader::addTransformer($sourceTransformer);
}

SourceTransformingLoader::load('./autoload.php');