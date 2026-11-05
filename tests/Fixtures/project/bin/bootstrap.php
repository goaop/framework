<?php
declare(strict_types=1);

/**
 * Boots the fixture project like an application bootstrap that builds its container: after init() it loads a class
 * of the include paths, Go\Tests\TestProject\Application\AddedAfterTheBuild, when a test has written it (issue #808)
 *
 * Used as the loader file of cache:warmup:aop: the class may be missing from the cache the warm-up is about to build.
 */

include __DIR__ . '/../web/index.php';

$addedClass = 'Go\Tests\TestProject\Application\AddedAfterTheBuild';
if (class_exists($addedClass)) {
    new $addedClass()->doSomething();
}
