<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

use Doctrine\Common\Annotations\AnnotationRegistry;
use Aspect\AwesomeAspectKernel;

include __DIR__ . '/../src/Go/Core/AspectKernel.php';
include __DIR__ . '/Aspect/AwesomeAspectKernel.php';

// Initialize demo aspect container
AwesomeAspectKernel::getInstance()->init(array(
    'debug'         => true,
    'appLoader'     => __DIR__ . '/autoload.php',
    'appDir'        => __DIR__ . '/../demos',
    'cacheDir'      => __DIR__ . '/cache',
    'autoloadPaths' =>  array(
        'Go'               => __DIR__ . '/../src',
        'TokenReflection'  => __DIR__ . '/../vendor/andrewsville/php-token-reflection/',
        'Doctrine\\Common' => __DIR__ . '/../vendor/doctrine/common/lib/',
        'Dissect'          => __DIR__ . '/../vendor/jakubledl/dissect/src/',
    ),
    // Composer way to autoload source code
    // 'autoloadPaths' => include __DIR__ . '/../vendor/composer/autoload_namespaces.php',
));

AnnotationRegistry::registerFile(__DIR__ . './Annotation/Cacheable.php');