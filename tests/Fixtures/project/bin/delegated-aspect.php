<?php
declare(strict_types=1);

/**
 * Boots the fixture project, then builds the application container and attaches it as the delegate of the
 * aspect container - the order of a real application, whose container only exists after the AOP kernel is up.
 * GreetingAspect is registered via addDelegatedService() and built by the application container on first use.
 */

use Go\Core\AspectKernel;
use Go\Tests\TestProject\Application\Greeter;
use Go\Tests\TestProject\Aspect\GreetingAspect;
use Go\Tests\TestProject\Container\ApplicationContainer;

include __DIR__ . '/../web/index.php';

$applicationContainer = new ApplicationContainer([
    GreetingAspect::class => static fn(): GreetingAspect => new GreetingAspect('Hello,'),
]);
$aspectContainer = AspectKernel::getInstance()->getContainer();
$aspectContainer->setDelegate($applicationContainer);

echo json_encode([
    'greeting'  => (new Greeter())->greet('World'),
    // The aspect container hands out a lazy proxy that forwards to the application's instance
    'delegated' => $aspectContainer->has(GreetingAspect::class),
], JSON_THROW_ON_ERROR);
