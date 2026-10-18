<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Showcase;

use Demo\Aspect\CachingAspect;
use Demo\Aspect\DynamicMethodsAspect;
use Demo\Aspect\EnumInterceptorAspect;
use Demo\Aspect\ErrorMonitoringAspect;
use Demo\Aspect\FluentInterfaceAspect;
use Demo\Aspect\FunctionInterceptorAspect;
use Demo\Aspect\HealthyLiveAspect;
use Demo\Aspect\IntroductionAspect;
use Demo\Aspect\LoggingAspect;
use Demo\Aspect\PrivateMethodAspect;
use Demo\Aspect\PropertyHooksAspect;
use Demo\Aspect\PropertyInterceptorAspect;
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
use Go\Aop\Proxy;
use InvalidArgumentException;

/**
 * List of all showcases of the demo
 *
 * The body of every scenario is shown on the page as the usage example, keep it short and readable.
 */
final class ShowcaseCatalog
{
    /**
     * @var array<string, Showcase>
     */
    private array $showcases = [];

    public function __construct()
    {
        foreach (self::define() as $showcase) {
            $this->showcases[$showcase->id] = $showcase;
        }
    }

    /**
     * @return list<Showcase>
     */
    public function all(): array
    {
        return array_values($this->showcases);
    }

    /**
     * @return array<string, list<Showcase>> Showcases indexed by the name of their group
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->showcases as $showcase) {
            $groups[$showcase->group][] = $showcase;
        }

        return $groups;
    }

    public function find(string $id): ?Showcase
    {
        return $this->showcases[$id] ?? null;
    }

    /**
     * @return iterable<Showcase>
     */
    private static function define(): iterable
    {
        yield new Showcase(
            id: 'loggable',
            group: 'Advices',
            title: 'Logging',
            summary: 'A Before advice logs every call of the methods marked with #[Loggable], protected and static ones included.',
            aspect: LoggingAspect::class,
            scenario: static function (): LoggingDemo {
                $example = new LoggingDemo();
                $example->execute('LoggingTask'); // Logging for dynamic methods
                LoggingDemo::runByName('StaticTask'); // Logging for static methods

                return $example;
            },
        );

        yield new Showcase(
            id: 'cacheable',
            group: 'Advices',
            title: 'Caching',
            summary: 'An Around advice caches the result of #[Cacheable] methods: the slow method runs only once.',
            aspect: CachingAspect::class,
            scenario: static function (): CacheableDemo {
                $example = new CacheableDemo();
                $result  = $example->getReport('Test'); // First call takes 0.1 second
                echo 'Result is: ', $result, PHP_EOL;

                $result = $example->getReport('Test1'); // This call is cached and the result is still 'Test'
                echo 'Result is: ', $result, PHP_EOL;

                return $example;
            },
        );

        yield new Showcase(
            id: 'after-throwing',
            group: 'Advices',
            title: 'Reporting exceptions',
            summary: 'An AfterThrowing advice reports the failures of a payment gateway, the caller still handles the exception.',
            aspect: ErrorMonitoringAspect::class,
            scenario: static function (): PaymentDemo {
                $example = new PaymentDemo();
                echo $example->charge('4111-1111', 50), PHP_EOL; // No exception, the advice is not called
                try {
                    $example->charge('4111-1111', 0); // The advice reports the exception, then it is rethrown
                } catch (InvalidArgumentException $exception) {
                    echo 'Caller handled the exception: ', $exception->getMessage(), PHP_EOL;
                }

                return $example;
            },
        );

        yield new Showcase(
            id: 'private-methods',
            group: 'Interceptors',
            title: 'Private and protected methods',
            summary: 'Every internal step of an order processor is intercepted, although only its public method is called.',
            aspect: PrivateMethodAspect::class,
            scenario: static function (): OrderProcessorDemo {
                $example = new OrderProcessorDemo();
                $total   = $example->process(['book' => 42.5, 'lamp' => 80.0]); // Only the public method is called here
                echo 'Order total: ', $total, PHP_EOL;

                return $example;
            },
        );

        yield new Showcase(
            id: 'property-interceptor',
            group: 'Interceptors',
            title: 'Property access',
            summary: 'An access() pointcut intercepts reads and writes of public, protected and private properties.',
            aspect: PropertyInterceptorAspect::class,
            scenario: static function (): PropertyDemo {
                $example = new PropertyDemo();
                echo $example->publicProperty, PHP_EOL; // Read public property
                $example->publicProperty = 987; // Write public property
                $example->showProtected();
                $example->setProtected(987);

                return $example;
            },
        );

        yield new Showcase(
            id: 'property-hooks',
            group: 'Interceptors',
            title: 'Property hooks',
            summary: 'Plain and asymmetric-visibility properties of PHP 8.4 are intercepted, a property with its own hook is left alone.',
            aspect: PropertyHooksAspect::class,
            scenario: static function (): ProductDemo {
                $example = new ProductDemo('go-aop-4'); // The own "set" hook of $sku uppercases the value
                $example->price = 19.99;
                $example->restock(5); // Reads and writes the asymmetric $stock property
                echo "Product {$example->sku} costs {$example->price}, {$example->stock} in stock", PHP_EOL;

                return $example;
            },
        );

        yield new Showcase(
            id: 'enum-interceptor',
            group: 'Interceptors',
            title: 'Enum methods',
            summary: 'Instance and static methods of a backed enum are intercepted just like the methods of a class.',
            aspect: EnumInterceptorAspect::class,
            scenario: static function (): OrderStatus {
                $example = OrderStatus::Pending;
                foreach (OrderStatus::cases() as $status) {
                    echo $status->label(), PHP_EOL;
                }
                $next = OrderStatus::next($example);
                echo 'Next status after pending: ', $next->name, PHP_EOL;

                return $example;
            },
        );

        yield new Showcase(
            id: 'function-interceptor',
            group: 'Interceptors',
            title: 'System functions',
            summary: 'Calls of array_*() and file_get_contents() inside a namespace are intercepted, an advice even replaces the result.',
            aspect: FunctionInterceptorAspect::class,
            scenario: static function (): FunctionDemo {
                $example = new FunctionDemo();
                echo 'Unique values: ', json_encode($example->testArrayFunctions(['test' => 1, 'code' => 2, 'more' => 1])), PHP_EOL;
                $example->testFileContent();

                return $example;
            },
        );

        yield new Showcase(
            id: 'dynamic-interceptor',
            group: 'Interceptors',
            title: 'Magic methods',
            summary: 'Calls going through __call() and __callStatic() are intercepted with the name of the called method.',
            aspect: DynamicMethodsAspect::class,
            scenario: static function (): DynamicMethodsDemo {
                $example = new DynamicMethodsDemo();
                $example->saveById(123); // Intercept magic dynamic method
                $example->load(456); // The advice filters out this method by name
                DynamicMethodsDemo::find(['id' => 124]); // Intercept magic static method

                return $example;
            },
        );

        yield new Showcase(
            id: 'fluent-interface',
            group: 'Advanced',
            title: 'Fluent interface',
            summary: 'An Around advice makes the setters return $this for every class implementing the FluentInterface marker.',
            aspect: FluentInterfaceAspect::class,
            scenario: static function (): UserFluentDemo {
                $example = new UserFluentDemo(); // Original class doesn't provide fluent interface for us
                if ($example instanceof Proxy) { // This check keeps the example working when AOP is disabled
                    $example
                        ->setName('John')
                        ->setSurname('Doe')
                        ->setPassword('root');
                } else {
                    echo 'Fluent interface is not available without AOP', PHP_EOL;
                }

                return $example;
            },
        );

        yield new Showcase(
            id: 'human-advices',
            group: 'Advanced',
            title: 'Human life advices',
            summary: 'Before and After advices take care of a healthy day: wash up before eating, clean the teeth after it.',
            aspect: HealthyLiveAspect::class,
            scenario: static function (): HumanDemo {
                $example = new HumanDemo();
                echo "Want to eat something, let's have a breakfast!", PHP_EOL;
                $example->eat();
                echo 'I should work to earn some money', PHP_EOL;
                $example->work();
                echo 'It was a nice day, go to bed', PHP_EOL;
                $example->sleep();

                return $example;
            },
        );

        yield new Showcase(
            id: 'dynamic-traits',
            group: 'Advanced',
            title: 'Dynamic traits and interfaces',
            summary: 'An introduction adds the Stringable interface and a trait implementing it to an existing class.',
            aspect: IntroductionAspect::class,
            scenario: static function (): IntroductionDemo {
                $example = new IntroductionDemo(); // Original class doesn't implement Stringable
                $example->testStringable();

                return $example;
            },
        );
    }
}
