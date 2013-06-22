<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

use Doctrine\Common\Annotations\AnnotationRegistry;
use Demo\Aspect\AwesomeAspectKernel;

include 'autoload.php';

// Initialize demo aspect container
AwesomeAspectKernel::getInstance()->init(array(
    'debug'         => true,
    'appDir'        => __DIR__ . '/../demos',
    'cacheDir'      => __DIR__ . '/cache',
));

AnnotationRegistry::registerFile(__DIR__ . './Demo/Annotation/Cacheable.php');