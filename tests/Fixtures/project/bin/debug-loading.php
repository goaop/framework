<?php
declare(strict_types=1);

/**
 * Boots the fixture project and prints, as JSON, which aspects init() loaded and how the autoloader resolves the
 * classes given as arguments: a plain path for a file the cache knows as untransformed, php://filter otherwise
 * (issue #745)
 */

use Go\Aop\Aspect;
use Go\Instrument\ClassLoading\AopComposerLoader;

$declaredBeforeInit = get_declared_classes();

include __DIR__ . '/../web/index.php';

$aspectsLoadedByInit = array_values(array_filter(
    array_diff(get_declared_classes(), $declaredBeforeInit),
    static fn(string $className): bool => is_subclass_of($className, Aspect::class),
));

$aopLoader = null;
foreach (spl_autoload_functions() as $loader) {
    if (is_array($loader) && $loader[0] instanceof AopComposerLoader) {
        $aopLoader = $loader[0];
    }
}
assert($aopLoader instanceof AopComposerLoader);

$classes = [];
foreach (array_slice($argv, 1) as $className) {
    $classes[$className] = [
        'file'     => $aopLoader->findFile($className),
        'loaded'   => class_exists($className),
        'fileName' => new ReflectionClass($className)->getFileName(),
    ];
}

echo json_encode(['aspectsLoadedByInit' => $aspectsLoadedByInit, 'classes' => $classes], JSON_THROW_ON_ERROR);
