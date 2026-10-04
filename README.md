Go! Aspect-Oriented Framework for PHP
-----------------

This framework brings **Aspect-Oriented Programming** to PHP — a powerful paradigm for handling cross-cutting concerns that don't fit neatly into traditional OOP like logging, caching, and security checks across hundreds of methods. **Go! AOP** solves this problem elegantly—define such behaviors as aspect classes **once**, and **apply them automatically everywhere** when needed. Your business logic stays clean, your infrastructure code stays organized.


![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/goaop/framework/phpunit.yml?branch=master)
[![Code Coverage](https://img.shields.io/codecov/c/github/goaop/framework/master)](https://app.codecov.io/gh/goaop/framework)
![PHPStan Badge](https://img.shields.io/badge/PHPStan-level%2010-brightgreen.svg?style=flat&link=https%3A%2F%2Fphpstan.org%2Fuser-guide%2Frule-levels)
[![GitHub release](https://img.shields.io/github/release/goaop/framework.svg)](https://github.com/goaop/framework/releases/latest)
[![Total Downloads](https://img.shields.io/packagist/dt/goaop/framework.svg)](https://packagist.org/packages/goaop/framework)
[![Daily Downloads](https://img.shields.io/packagist/dd/goaop/framework.svg)](https://packagist.org/packages/goaop/framework)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%208.4-8892BF.svg)](https://www.php.net/supported-versions.php)
[![License](https://img.shields.io/packagist/l/goaop/framework.svg)](https://packagist.org/packages/goaop/framework)
[![Sponsor](https://img.shields.io/badge/Sponsor-❤️-lightgray?style=flat&logo=github)](https://github.com/sponsors/lisachenko)

## ✨ Features

### 🔌 Zero Dependencies, Pure PHP

 - **No PECL/PIE extensions required** — Forget about `php-aop`, `runkit`, `uopz`, or any other low-level extensions. Go! AOP is written in **100% pure PHP** — just `composer require` and you're ready. No compilation, no system dependencies, no deployment headaches.

 - **Zero `eval()` calls** — Your architecture team will love this. Framework never uses `eval()`, `create_function()` constructions for dynamic code execution. All transformations produce **static PHP files** that can be reviewed, scanned by security tools, and audited. No hidden code generation at runtime.

 - **PHPStan Level 10** — The entire codebase passes **PHPStan's strictest analysis level** — maximum type safety, no mixed types escaping, full type-aware support. This means fewer bugs, better IDE autocompletion, and confidence that the framework won't introduce type errors into your application.

### 🎯 Powerful Core Interception Capabilities

The framework provides powerful core interception capabilities that can be used to hook into any method in your application:

| Feature                                    | Support |
|--------------------------------------------|:-------:|
| Interception of public & protected methods |    ✅    |
| Interception of static and final methods   |    ✅    |
| Interception of private methods            |    ✅    |
| Interception of methods in `final` classes |    ✅    |
| Interception of trait methods              |    ✅    |
| Interception of enum methods (PHP 8.1+)    |    ✅    |
| Before, After and Around type of hooks     |    ✅    |

### 🧷 Property interception (PHP 8.4+)

Go! AOP intercepts field access via native PHP 8.4 property hooks on generated proxy classes.

- Supported targets:
  - properties declared in the woven class itself
  - inherited **public/protected** properties from parent classes
- Not supported (intentionally skipped):
  - `static`, `readonly`, and already-hooked properties
  - inherited `final` properties from parent classes
  - inherited `private` properties

For array-typed intercepted properties, the proxy emits only a by-reference `&get` hook (without `set`) to keep
indirect modification operations like `array_push($this->items, ...)` valid.

Generated hooks pass values to the field access joinpoint by value (`read()`/`write()`), only the `&get` hook of
array properties uses `readByReference()`. For typed properties without a default value, generated hooks include an
initialization guard:

```php
if (isset($this->property) || $__joinPoint->getField()->isInitialized($this)) {
    return $__joinPoint->read($this, $this->property);
} else {
    return $__joinPoint->read($this);
}
```

> **See also:** [PHP 8.4 Feature Support & Known Limitations](docs/php84-limitations.md) for detailed information about property hooks, readonly properties, lazy objects, and other PHP 8.4 features.
>
> [PHP 8.5 Feature Support & Known Limitations](docs/php85-limitations.md) covers the PHP 8.5 audit results.

### 🛠️ Developer Experience

 - **Rich pointcut syntax** — Express complex matching rules with an intuitive, readable grammar. Target methods by visibility, name patterns, attributes, class hierarchy, and more — all in a single expression like `execution(public **->save*(*))`.

 - **Full XDebug support** — Unlike other AOP solutions that generate unreadable proxy code, the framework produces clean, debuggable PHP. Set breakpoints directly in your aspects or original classes — step through code naturally, inspect variables, and debug as if AOP wasn't there.

 - **Readable weaved code** — No magic methods, no `__call()` indirection or runtime proxies. The transformed source is plain PHP that you can read, understand, and audit. What you see in the cache is what gets executed.

 - **Framework-agnostic** — integrates with popular frameworks and vanilla PHP. Works equally well in legacy applications or greenfield projects — no architectural changes required.

### ⚡ Production Ready

 - **Lightning fast** — Quick start in just **few ms**. Aspects are initialized once and cached, so subsequent requests have virtually zero initialization overhead.

 - **Opcode cache friendly** — First-class support for **OPcache**. Transformed files and classes are stored as plain PHP files, fully optimized by your opcode cache just like regular code.

 - **Smart caching** — Advices are woven as **first-class callables** pointing straight at your aspect methods, joinpoints are resolved at compile-time and cached in the generated code — eliminating runtime reflection costs and lazy advisor indirection.

 - **No runtime overhead** — Zero runtime attribute parsing, no slow `__call` methods, no proxy objects wrapping your instances. Method interception happens through direct, inlined PHP code — as fast as handwritten cross-cutting code. **Zero** overhead for non-intercepted methods.


What is AOP?
------------

[Aspect-Oriented Programming (AOP)](http://en.wikipedia.org/wiki/Aspect-oriented_programming) is a programming paradigm that complements Object-Oriented Programming by solving a fundamental problem: **cross-cutting concerns**.

### The Problem with Traditional OOP

In OOP, we organize code into classes with clear responsibilities. But some behaviors refuse to fit neatly into this model — they cut *across* many classes:

- **Logging** — you need it in dozens of methods across your application
- **Caching** — scattered throughout services and repositories
- **Security checks** — repeated before every sensitive operation
- **Transaction management** — wrapping multiple database operations
- **Performance monitoring** — measuring execution time everywhere

With pure OOP, you end up copying the same code into hundreds of places. When requirements change, you hunt through the entire codebase. This is called **code scattering** and **code tangling** — and it violates the DRY and DDD principle at scale.

### The AOP Solution

AOP introduces a simple but powerful idea: **define cross-cutting behavior once, apply it automatically wherever needed**.

Think of it like life advice. Your mentor doesn't follow you around repeating "check your inputs before every decision." Instead, they give you one piece of advice that you apply in many situations. AOP works the same way — you write the advice once, and the framework applies it at the right moments.

### Core Concepts

| Concept        | What It Means                                                                       | Real-World Analogy                                                    |
|----------------|-------------------------------------------------------------------------------------|-----------------------------------------------------------------------|
| **Aspect**     | A module containing cross-cutting logic (logging, caching, etc.)                    | A chapter in a guidebook covering the document signing process        |
| **Join Point** | A specific moment in code execution — method call, property access, object creation | A decision point in your day where advice could apply                 |
| **Advice**     | The actual code that runs at a join point                                           | The specific guidance: "Before signing the document, read everything" |
| **Pointcut**   | A pattern that selects which join points to target                                  | The rule for *when* advice applies: "Before signing *any* contract"   |
| **Weaving**    | The process of applying aspects to your code                                        | The mentor's words becoming part of your thinking                     |

### Types of Advice

Just like life advice can be applied at different moments, AOP advice has different timing:

| Advice Type         | When It Runs                     | Example Use Case                   |
|---------------------|----------------------------------|------------------------------------|
| **Before**          | Right before the method executes | Validate input, check permissions  |
| **After (Finally)** | Always, regardless of outcome    | Release resources, stop timers     |
| **Around**          | Wraps the entire execution       | Caching, transactions, retry logic |
| **After Throwing**  | When an exception is thrown      | Log errors, send alerts            |

**Around advice** is the most powerful — it controls **whether the original method runs at all**, can modify arguments, change return values, or handle exceptions.

### Advanced: Introductions

Go! AOP can do more than intercept behavior — it can **add entirely new capabilities** to existing classes. This is called an **Introduction** (or inter-type declaration).

Want all your DTOs to implement `Serializable`? Instead of modifying every class, declare it once in an aspect — Go! AOP adds the interface and implementation automatically. No inheritance hierarchies, no code duplication.

### How Go! AOP Works

Unlike frameworks requiring special compilation steps, Go! AOP performs **runtime weaving** — it transforms your classes when they're loaded into PHP. No build process, no generated files to commit. Your original source code stays untouched, and the framework handles everything transparently.

Installation
------------

> Upgrading from 3.x? Follow the [upgrade guide](UPGRADE-4.0.md).

Go! AOP framework can be installed with composer. Installation is quite easy:

1. Download the framework using composer
2. Create an application aspect kernel
3. Configure the aspect kernel in the front controller
4. Create an aspect
5. Register the aspect in the aspect kernel

Step 0 is optional: it lets you try the demo examples first.

### Step 0 (optional): Try demo examples in the framework

The demos are not part of the composer package, so clone the repository and install its dependencies:

```bash
git clone https://github.com/goaop/framework.git
cd framework
composer install
php -S localhost:8080 -t demos
```
Then open http://localhost:8080 in your browser and look at the demo examples before installing the framework in your project.

### Step 1: Download the library using composer

Ask composer to download the latest version of Go! AOP framework with its dependencies by running the command:

```bash
composer require goaop/framework
```

Composer will install the framework to your project's `vendor/goaop/framework` directory.


### Step 2: Create an application aspect kernel

The aim of this framework is to provide easy AOP integration for your application.
You have to first create the `AspectKernel` class
for your application. This class will manage all aspects of your
application in one place.

The framework provides base class to make it easier to create your own kernel.
To create your application kernel, extend the abstract class `Go\Core\AspectKernel`

```php
<?php
// app/ApplicationAspectKernel.php

use Go\Core\AspectKernel;
use Go\Core\AspectContainer;

/**
 * Application Aspect Kernel
 */
class ApplicationAspectKernel extends AspectKernel
{

    /**
     * Configure an AspectContainer with advisors, aspects and pointcuts
     */
    protected function configureAop(AspectContainer $container): void
    {
    }
}
```

### Step 3: Configure the aspect kernel in the front controller

To configure the aspect kernel, call `init()` method of kernel instance.

```php
<?php
// public/index.php

include __DIR__ . '/../vendor/autoload.php'; // use composer

// Initialize an application aspect container
$applicationAspectKernel = ApplicationAspectKernel::getInstance();
$applicationAspectKernel->init([
    'debug'        => true, // use 'false' for production mode
    'appDir'       => __DIR__ . '/..', // Application root directory
    'cacheDir'     => __DIR__ . '/../var/cache/aop', // Cache directory
    // Include paths restricts the directories where aspects should be applied, or empty for all source files
    'includePaths' => [
        __DIR__ . '/../src/'
    ]
]);
```

### Step 4: Create an aspect

Aspect is the key element of AOP philosophy. Go! AOP framework just uses simple PHP classes for declaring aspects, which makes it possible to use all features of OOP for aspect classes.
Advices are declared as **public methods** of the aspect — the framework weaves them into your code as [first-class callables](https://www.php.net/manual/en/functions.first_class_callable_syntax.php), so every advice must be callable on the aspect instance from the outside (a `protected` or `private` advice method is rejected during aspect loading).
As an example, let's intercept all the methods and display their names:

```php
<?php

// Aspect/MonitorAspect.php

namespace Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Before;

/**
 * Monitor aspect
 */
class MonitorAspect implements Aspect
{

    /**
     * Method that will be called before real method
     */
    #[Before("execution(public Example->*(*))")]
    public function beforeMethodExecution(MethodInvocation $invocation): void
    {
        echo 'Calling Before Interceptor for: ',
            $invocation,
            ' with arguments: ',
            json_encode($invocation->getArguments()),
            "<br>\n";
    }
}
```

Easy, isn't it? We declared here that we want to install a hook before the execution of
all dynamic public methods in the class Example. This is done with the help of attribute
`#[Before("execution(public Example->*(*))")]`
Hooks can be of any types, you will see them later.

#### Advices are first-class callables

There is no magic behind applying an aspect. For every intercepted method the framework
generates a plain, debuggable interceptor chain in which each advice is referenced as a
**closure created with first-class callable syntax** right on the aspect instance:

```php
static $__joinPoint = InterceptorInjector::forMethod(
    self::class,
    'doSomething',
    [
        Interceptor::before(The::aspect(MonitorAspect::class)->beforeMethodExecution(...)),
    ],
    $this->doSomethingOriginalAlias(...),
);
```

`The::aspect()` fetches the aspect instance from the aspect container, and
`->beforeMethodExecution(...)` is the very advice method you wrote above — you can
Ctrl-click it in your IDE, set a breakpoint inside it, and step through the woven code as if
it were handwritten. This direct wiring is the main way advices are applied.

Advices that are registered in the container as plain closures (rather than aspect methods)
are woven through the lazy `The::advice()` accessor instead, which resolves the advisor by
its identifier and unwraps it down to the raw advice closure:

```php
Interceptor::around(The::advice('advisor.Demo\Aspect\DynamicMethodsAspect->aroundMagicMethods')),
```

### Step 5: Register the aspect in the aspect kernel

An aspect is a typical container service. Add it in the `configureAop()` method of the
kernel, either eagerly as an instance or - preferably - as a deferred definition that is
only constructed when one of its advices actually runs:

```php
<?php
// app/ApplicationAspectKernel.php

use Aspect\MonitorAspect;
use Go\Core\AspectContainer;

//...

    protected function configureAop(AspectContainer $container): void
    {
        // Deferred (recommended): constructed on first use
        $container->addLazyService(MonitorAspect::class, static fn() => new MonitorAspect());

        // Eager alternative: $container->add(MonitorAspect::class, new MonitorAspect());
    }

//...
```

### Optional configurations

#### Weaving Doctrine entities

Doctrine ORM (3.6+) maps a woven entity as is: the woven class keeps its name and all mapping
attributes, and the original class body lives in a trait that Doctrine never maps. Two things
need to be configured.

**Entity discovery.** Given plain directories, Doctrine includes the entity source files directly,
which bypasses the weaver (the entity loses its aspects), and it misses entities that were already
loaded through the autoloader. Let the mapping driver discover entities through
`Go\Bridge\Doctrine\WovenEntityClassLocator` instead: it reads class names from the source files
and loads each class through the autoloader, which serves the woven version.

```php
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Go\Bridge\Doctrine\WovenEntityClassLocator;

$configuration->setMetadataDriverImpl(
    new AttributeDriver(WovenEntityClassLocator::createFromDirectories([__DIR__ . '/src/Entity'])),
);
```

**Intercepted properties.** An intercepted property is re-declared with native property hooks,
which Doctrine supports only with native lazy objects. Enable them and, optionally, register
`Go\Bridge\Doctrine\MetadataLoadInterceptor`, which reports a woven entity with intercepted mapped
properties when native lazy objects are off, instead of failing on the first lazy proxy:

```php
use Doctrine\ORM\Events;
use Go\Bridge\Doctrine\MetadataLoadInterceptor;

$configuration->enableNativeLazyObjects(true);
$eventManager->addEventListener(Events::loadClassMetadata, new MetadataLoadInterceptor());
```

Doctrine reads and writes the raw property values while hydrating and flushing, so property
advices only run for accesses made by your own code, never for Doctrine's internal ones.

Documentation
-------------

- [Configuration and deployment](docs/configuration.md): kernel options, `Features` flags, the `bin/aspect` console
  commands, the production deployment recipe and custom transformers or node rewriters
- [Pointcut syntax](docs/pointcuts.md): every pointcut type, wildcards, modifiers, return types and operators
- [Advices](docs/advices.md): advice types, joinpoints, property interception, introductions, function and
  object-creation interception
- [PHP 8.4 limitations](docs/php84-limitations.md) and [PHP 8.5 limitations](docs/php85-limitations.md)
- [Upgrading from 3.x](UPGRADE-4.0.md) and the [changelog](CHANGELOG.md)

### Contribution

To contribute changes, see the [contributing guide](CONTRIBUTING.md)
