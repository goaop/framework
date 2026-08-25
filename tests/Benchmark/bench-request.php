<?php

declare(strict_types=1);
/*
 * Go! AOP framework — boot-time benchmark request.
 *
 * Simulates one application request against the fixture project: boots the
 * aspect kernel exactly like tests/Fixtures/project/web/index.php, then
 * autoloads a representative set of woven application classes and triggers
 * one intercepted call (first advice binding).
 *
 * Environment:
 *   GO_AOP_CONFIGURATION  configuration profile (default|production)
 *   GO_AOP_BENCH_SPANS    path to write the span/counter JSON report
 */

use Go\Instrument\BootTimer;
use Go\Tests\TestProject\Application\ClassUsingTrait;
use Go\Tests\TestProject\Application\ClassWithPrivateMethods;
use Go\Tests\TestProject\Application\FinalClass;
use Go\Tests\TestProject\Application\Main;
use Go\Tests\TestProject\Application\Php84PropertyHooksClass;
use Go\Tests\TestProject\Application\SimpleEnum;

require_once __DIR__ . '/../../vendor/autoload.php';

$configuration = getenv('GO_AOP_CONFIGURATION') ?: 'default';
$settings      = require __DIR__ . '/../Fixtures/project/web/configuration.php';

$kernel = $settings[$configuration]['kernel']::getInstance();
$kernel->init($settings[$configuration]);

// From here on the kernel is booted; everything below models the application
// doing its work: autoloading woven classes and hitting one intercepted method.
BootTimer::begin('app.autoloadWovenClasses');
$classes = [
    Main::class,
    ClassUsingTrait::class,
    FinalClass::class,
    ClassWithPrivateMethods::class,
    Php84PropertyHooksClass::class,
    SimpleEnum::class,
];
foreach ($classes as $class) {
    class_exists($class) || enum_exists($class);
}
BootTimer::end();

BootTimer::begin('app.firstInterceptedCall');
ob_start();
(new Main())->doSomething();
ob_end_clean();
BootTimer::end();
