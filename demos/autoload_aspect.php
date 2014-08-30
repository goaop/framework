<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

use Doctrine\Common\Annotations\AnnotationRegistry;
use Demo\Aspect\AwesomeAspectKernel;
use Go\Aop\Features;

include __DIR__ .'/autoload.php';

// Initialize demo aspect container
$defaultFeatures = AwesomeAspectKernel::getDefaultFeatures();
AwesomeAspectKernel::getInstance()->init(array(
    'debug'    => true,
    'appDir'   => __DIR__ . '/../demos',
    'cacheDir' => __DIR__ . '/cache',

    'features' => $defaultFeatures | Features::INTERCEPT_FUNCTIONS,
));

AnnotationRegistry::registerFile(__DIR__ . '/Demo/Annotation/Cacheable.php');