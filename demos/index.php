<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

use Doctrine\Common\Annotations\AnnotationRegistry;

include '../src/Go/Core/AspectKernel.php';
include 'DemoAspectKernel.php';

// Initialize demo aspect container
DemoAspectKernel::getInstance()->init(array(
    // Configuration for autoload namespaces
    'autoload' => array(
        'Go'               => realpath(__DIR__ . '/../src'),
        'TokenReflection'  => realpath(__DIR__ . '/../vendor/andrewsville/php-token-reflection/'),
        'Doctrine\\Common' => realpath(__DIR__ . '/../vendor/doctrine/common/lib/')
    ),
    // Default application directory
    'appDir' => __DIR__ . '/../demos',
    // Cache directory for Go! generated classes
    'cacheDir' => __DIR__ . '/cache',
    // Include paths for aspect weaving
    'includePaths' => array(),
    'debug' => true
));

AnnotationRegistry::registerFile('./Annotation/Cacheable.php');

$class = new Example('test');
if ($class instanceof Serializable) {
    echo "Yeah, Example is serializable!", "<br>", PHP_EOL;
    $ref = new ReflectionClass('Example');
    var_dump($ref->getTraitNames(), $ref->getInterfaceNames());
} else {
    echo "Ooops, Example isn't serializable!", "<br>", PHP_EOL;
}
unserialize(serialize($class));
$class->publicHello();
for ($i=10; $i--; ) {
    $class->cacheMe(0.2);
}