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
));

AnnotationRegistry::registerFile(__DIR__ . './Annotation/Cacheable.php');