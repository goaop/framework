<?php
declare(strict_types=1);

/**
 * Boots the fixture project, then builds the application container and attaches it as the delegate of the
 * aspect container - the order of a real application, whose container only exists after the AOP kernel is up.
 * GreetingAspect is registered via addLazyService(), its factory takes Salutation from the application container.
 */

use Go\Core\AspectKernel;
use Go\Tests\TestProject\Application\Greeter;
use Go\Tests\TestProject\Application\Salutation;
use Go\Tests\TestProject\Container\ApplicationContainer;

include __DIR__ . '/../web/index.php';

$applicationContainer = new ApplicationContainer([
    Salutation::class => static fn(): Salutation => new Salutation('Hello,'),
]);
$aspectContainer = AspectKernel::getInstance()->getContainer();
$aspectContainer->setDelegate($applicationContainer);

echo json_encode([
    'greeting'  => (new Greeter())->greet('World'),
    'delegated' => $aspectContainer->has(Salutation::class),
], JSON_THROW_ON_ERROR);
