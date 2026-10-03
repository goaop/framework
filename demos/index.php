<?php
declare(strict_types=1);

/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

use Demo\Example\CacheableDemo;
use Demo\Example\DynamicMethodsDemo;
use Demo\Example\FunctionDemo;
use Demo\Example\HumanDemo;
use Demo\Example\IntroductionDemo;
use Demo\Example\LoggingDemo;
use Demo\Example\OrderProcessorDemo;
use Demo\Example\OrderStatus;
use Demo\Example\PaymentDemo;
use Demo\Example\ProductDemo;
use Demo\Example\PropertyDemo;
use Demo\Example\UserFluentDemo;
use Demo\Highlighter;
use Go\Aop\Proxy;
use Go\Instrument\Transformer\MagicConstantTransformer;

$isAOPDisabled = isset($_COOKIE['aop_on']) && $_COOKIE['aop_on'] === 'false';
include __DIR__ . ($isAOPDisabled ? '/../vendor/autoload.php' : '/autoload_aspect.php');

$showCase   = $_GET['showcase'] ?? 'default';
$example    = null;
$aspectName = '';

// The output of a showcase is captured to be escaped inside the page
ob_start();

switch ($showCase) {
    case 'cacheable':
        $aspectName = 'Demo\Aspect\CachingAspect';

        $example = new CacheableDemo();
        $result  = $example->getReport('Test'); // First call will take 0.1 second
        echo "Result is: ", $result, PHP_EOL;

        $result = $example->getReport('Test1'); // This call is cached and result should be 'Test'
        echo "Result is: ", $result, PHP_EOL;
        break;

    case 'loggable':
        $aspectName = 'Demo\Aspect\LoggingAspect';

        $example = new LoggingDemo();
        $example->execute('LoggingTask'); // Logging for dynamic methods
        LoggingDemo::runByName('StaticTask'); // Logging for static methods
        break;

    case 'after-throwing':
        $aspectName = 'Demo\Aspect\ErrorMonitoringAspect';

        $example = new PaymentDemo();
        echo $example->charge('4111-1111', 50), PHP_EOL; // No exception, the advice is not called
        try {
            $example->charge('4111-1111', 0); // The advice reports the exception, then it is rethrown
        } catch (InvalidArgumentException $exception) {
            echo 'Caller handled the exception: ', $exception->getMessage(), PHP_EOL;
        }
        break;

    case 'private-methods':
        $aspectName = 'Demo\Aspect\PrivateMethodAspect';

        $example = new OrderProcessorDemo();
        $total   = $example->process(['book' => 42.5, 'lamp' => 80.0]); // Only the public method is called here
        echo 'Order total: ', $total, PHP_EOL;
        break;

    case 'property-interceptor':
        $aspectName = 'Demo\Aspect\PropertyInterceptorAspect';

        $example = new PropertyDemo();
        echo $example->publicProperty, PHP_EOL; // Read public property
        $example->publicProperty = 987; // Write public property
        $example->showProtected();
        $example->setProtected(987);
        break;

    case 'property-hooks':
        $aspectName = 'Demo\Aspect\PropertyHooksAspect';

        $example = new ProductDemo('go-aop-4'); // The own "set" hook of $sku uppercases the value
        $example->price = 19.99;
        $example->restock(5); // Reads and writes the asymmetric $stock property
        echo "Product {$example->sku} costs {$example->price}, {$example->stock} in stock", PHP_EOL;
        break;

    case 'enum-interceptor':
        $aspectName = 'Demo\Aspect\EnumInterceptorAspect';

        $example = OrderStatus::Pending;
        foreach (OrderStatus::cases() as $status) {
            echo $status->label(), PHP_EOL;
        }
        $next = OrderStatus::next($example);
        echo 'Next status after pending: ', $next->name, PHP_EOL;
        break;

    case 'dynamic-interceptor':
        $aspectName = 'Demo\Aspect\DynamicMethodsAspect';

        $example = new DynamicMethodsDemo();
        $example->saveById(123); // intercept magic dynamic method
        $example->load(456); // the advice filters out this method by name
        DynamicMethodsDemo::find(['id' => 124]); // intercept magic static method
        break;

    case 'function-interceptor':
        $aspectName = 'Demo\Aspect\FunctionInterceptorAspect';

        $example = new FunctionDemo();
        echo 'Unique values: ', json_encode($example->testArrayFunctions(['test' => 1, 'code' => 2, 'more' => 1])), PHP_EOL;
        $example->testFileContent();
        break;

    case 'fluent-interface':
        $aspectName = 'Demo\Aspect\FluentInterfaceAspect';

        $example = new UserFluentDemo(); // Original class doesn't provide fluent interface for us
        if ($example instanceof Proxy) { // This check is to prevent fatal errors when AOP is disabled
            $example
                ->setName('John')
                ->setSurname('Doe')
                ->setPassword('root');
        } else {
            echo "Fluent interface is not available without AOP", PHP_EOL;
        }
        break;

    case 'human-advices':
        $aspectName = 'Demo\Aspect\HealthyLiveAspect';

        $example = new HumanDemo();
        echo "Want to eat something, let's have a breakfast!", PHP_EOL;
        $example->eat();
        echo "I should work to earn some money", PHP_EOL;
        $example->work();
        echo "It was a nice day, go to bed", PHP_EOL;
        $example->sleep();
        break;

    case 'dynamic-traits':
        $aspectName = 'Demo\Aspect\IntroductionAspect';

        $example = new IntroductionDemo(); // Original class doesn't implement Stringable
        $example->testStringable();
        break;

    default:
}

$output = (string) ob_get_clean();

// Woven classes are loaded from the cache: the page shows their original source files
$sourceFile = static fn(string|false $path): string => MagicConstantTransformer::resolveFileName((string) $path);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Go! AOP Demo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
</head>
<body>

<header>
    <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="https://go.aopphp.com/">Go! AOP</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-navigation"
                    aria-controls="main-navigation" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="main-navigation">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">Examples</a>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">Advices</h6></li>
                            <li><a class="dropdown-item" href="?showcase=loggable">Logging</a></li>
                            <li><a class="dropdown-item" href="?showcase=cacheable">Caching</a></li>
                            <li><a class="dropdown-item" href="?showcase=after-throwing">Reporting exceptions (AfterThrowing)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Interceptors</h6></li>
                            <li><a class="dropdown-item" href="?showcase=private-methods">Intercepting private and protected methods</a></li>
                            <li><a class="dropdown-item" href="?showcase=property-interceptor">Intercepting access to the properties</a></li>
                            <li><a class="dropdown-item" href="?showcase=property-hooks">Property hooks and asymmetric visibility</a></li>
                            <li><a class="dropdown-item" href="?showcase=enum-interceptor">Intercepting enum methods</a></li>
                            <li><a class="dropdown-item" href="?showcase=function-interceptor">Intercepting system functions</a></li>
                            <li><a class="dropdown-item" href="?showcase=dynamic-interceptor">Intercepting magic methods (__call and __callStatic)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Advanced</h6></li>
                            <li><a class="dropdown-item" href="?showcase=fluent-interface">Fluent interface</a></li>
                            <li><a class="dropdown-item" href="?showcase=human-advices">Human life advices</a></li>
                            <li><a class="dropdown-item" href="?showcase=dynamic-traits">Dynamic traits and interfaces</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="https://github.com/goaop/framework/tree/master/docs" target="_blank" rel="noopener">Documentation</a></li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">AOP solutions</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="https://github.com/php-deal/framework" target="_blank" rel="noopener">PhpDeal - Design by Contract framework</a></li>
                            <li><a class="dropdown-item" href="https://github.com/Codeception/AspectMock" target="_blank" rel="noopener">AspectMock - Testing framework</a></li>
                            <li><a class="dropdown-item" href="https://github.com/lisachenko/warlock" target="_blank" rel="noopener">Warlock - Go! AOP + Symfony DiC</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown" aria-expanded="false">Videos &amp; presentations</a>
                        <ul class="dropdown-menu">
                            <li><h6 class="dropdown-header">Videos</h6></li>
                            <li><a class="dropdown-item" href="https://www.youtube.com/watch?v=aZ_9PuHemBk&amp;t=1283" target="_blank" rel="noopener">Advanced logging in PHP</a></li>
                            <li><a class="dropdown-item" href="https://www.youtube.com/watch?v=BXKQ99-78bI" target="_blank" rel="noopener">Aspect-Oriented Programming in PHP</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Slides</h6></li>
                            <li><a class="dropdown-item" href="https://www.slideshare.net/lisachenko/solving-crosscutting-concerns-in-php" target="_blank" rel="noopener">Solving Cross-Cutting Concerns in PHP (at DPC16)</a></li>
                            <li><a class="dropdown-item" href="https://www.slideshare.net/lisachenko/weaving-aspects-in-php-with-the-help-of-go-aop-library" target="_blank" rel="noopener">Weaving aspects in PHP with the help of Go! AOP library</a></li>
                            <li><a class="dropdown-item" href="https://www.slideshare.net/lisachenko/aspect-oriented-programming-in-php" target="_blank" rel="noopener">Aspect-Oriented Programming in PHP (Russian)</a></li>
                        </ul>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <span class="navbar-text">AOP:</span>
                    <?php if ($isAOPDisabled): ?>
                    <button type="button" class="btn btn-sm btn-danger" id="aop_on" data-enabled="false">Off</button>
                    <?php else: ?>
                    <button type="button" class="btn btn-sm btn-info" id="aop_on" data-enabled="true">On</button>
                    <?php endif; ?>
                    <a class="nav-link" href="https://github.com/goaop/framework" target="_blank" rel="noopener">Fork me</a>
                </div>
            </div>
        </div>
    </nav>
</header>

<main class="container">

    <h1>Welcome</h1>
    <p class="lead">This demo shows examples of AOP usage.</p>

    <div class="card mb-4">
        <div class="card-body">
            <p class="card-text">
                Choose one of the examples from the navigation menu.
                You can also run this code with Xdebug.
            </p>
            <pre class="mb-0"><?= htmlspecialchars($output) ?></pre>
        </div>
    </div>

    <?php if ($aspectName !== ''): ?>
    <details class="card mb-3">
        <summary class="card-header">Source code of the aspect</summary>
        <div class="card-body">
            <?php Highlighter::highlight($sourceFile(new ReflectionClass($aspectName)->getFileName())); ?>
        </div>
    </details>
    <?php endif; ?>

    <?php if ($example !== null): ?>
    <details class="card mb-3">
        <summary class="card-header">Source code of the class</summary>
        <div class="card-body">
            <?php Highlighter::highlight($sourceFile(new ReflectionObject($example)->getFileName())); ?>
        </div>
    </details>
    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
    document.getElementById('aop_on').addEventListener('click', function () {
        const enabled = this.dataset.enabled === 'true';
        document.cookie = 'aop_on=' + (!enabled) + '; path=/; SameSite=Lax';
        window.location.reload();
    });
</script>
</body>
</html>
