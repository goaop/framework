<?php
declare(strict_types=1);

/**
 * Boots the fixture project, calls intercepted methods on fresh instances, drops them and prints, as JSON,
 * whether each instance was released (issue #673): a shared joinpoint must not keep its first caller alive
 */

use Go\Tests\TestProject\Application\ClassUsingTrait;
use Go\Tests\TestProject\Application\Main;

include __DIR__ . '/../web/index.php';

$released = [];
foreach ([
    'own method'   => static fn(): object => new ClassUsingTrait(),
    'trait method' => static fn(): object => new ClassUsingTrait(),
    'main'         => static fn(): object => new Main(),
] as $case => $factory) {
    $instance = $factory();
    ob_start();
    match ($case) {
        'own method'   => $instance->ownMethod(),
        'trait method' => $instance->doSomeTraitBehavior(),
        'main'         => $instance->doSomething(),
    };
    ob_end_clean();
    $reference = WeakReference::create($instance);
    unset($instance);
    gc_collect_cycles();
    $released[$case] = $reference->get() === null;
}

echo json_encode($released, JSON_THROW_ON_ERROR);
