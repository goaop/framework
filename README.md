Go! Aspect-Oriented Framework for PHP
-----------------

This framework brings **Aspect-Oriented Programming** to PHP — a powerful paradigm for handling cross-cutting concerns that don't fit neatly into traditional OOP like logging, caching, and security checks across hundreds of methods. **Go! AOP** solves this problem elegantly—define such behaviors as aspect classes **once**, and **apply them automatically everywhere** when needed. Your business logic stays clean, your infrastructure code stays organized.


![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/goaop/framework/phpunit.yml?branch=master)
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

For typed properties without a default value, generated `get` hooks include an initialization guard:

```php
if ($fieldAccess->getField()->isInitialized($this)) {
    $value = &$fieldAccess->__invoke($this, FieldAccessType::READ, $this->property);
} else {
    $value = $fieldAccess->__invoke($this, FieldAccessType::READ);
}
```

### 🛠️ Developer Experience

 - **Rich pointcut syntax** — Express complex matching rules with an intuitive, readable grammar. Target methods by visibility, name patterns, annotations, class hierarchy, and more — all in a single expression like `execution(public **->save*(*))`.

 - **Full XDebug support** — Unlike other AOP solutions that generate unreadable proxy code, the framework produces clean, debuggable PHP. Set breakpoints directly in your aspects or original classes — step through code naturally, inspect variables, and debug as if AOP wasn't there.

 - **Readable weaved code** — No magic methods, no `__call()` indirection or runtime proxies. The transformed source is plain PHP that you can read, understand, and audit. What you see in the cache is what gets executed.

 - **Framework-agnostic** — integrates with popular frameworks and vanilla PHP. Works equally well in legacy applications or greenfield projects — no architectural changes required.

### ⚡ Production Ready

 - **Lightning fast** — Quick start in just **few ms**. Aspects are initialized once and cached, so subsequent requests have virtually zero initialization overhead.

 - **Opcode cache friendly** — First-class support for **OPcache**. Transformed files and classes are stored as plain PHP files, fully optimized by your opcode cache just like regular code.

 - **Smart caching** — Lazy loading of advice and aspects — only what's needed gets loaded. Joinpoints are resolved at compile-time and cached, eliminating runtime reflection costs.

 - **No runtime overhead** — Zero runtime annotation parsing, no slow `__call` methods, no proxy objects wrapping your instances. Method interception happens through direct, inlined PHP code — as fast as handwritten cross-cutting code. **Zero** overhead for non-intercepted methods.


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

Go! AOP framework can be installed with composer. Installation is quite easy:

1. Download the framework using composer
2. Create an application aspect kernel
3. Configure the aspect kernel in the front controller
4. Create an aspect
5. Register the aspect in the aspect kernel

### Step 0 (optional): Try demo examples in the framework

Ask composer to create a new project in empty directory:

```bash
composer create-project goaop/framework
```
After that configure your web server to `demos/` folder and open it in your browser. Then you can look at some demo examples before going deeper into installing it in your project.

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

### 3. Configure the aspect kernel in the front controller

To configure the aspect kernel, call `init()` method of kernel instance.

```php
<?php

include __DIR__ . '/vendor/autoload.php'; // use composer

// Initialize an application aspect container
$applicationAspectKernel = ApplicationAspectKernel::getInstance();
$applicationAspectKernel->init([
    'debug'        => true, // use 'false' for production mode
    'appDir'       => __DIR__ . '/..', // Application root directory
    'cacheDir'     => __DIR__ . '/path/to/cache/for/aop', // Cache directory
    // Include paths restricts the directories where aspects should be applied, or empty for all source files
    'includePaths' => [
        __DIR__ . '/../src/'
    ]
]);
```

### 4. Create an aspect

Aspect is the key element of AOP philosophy. Go! AOP framework just uses simple PHP classes for declaring aspects, which makes it possible to use all features of OOP for aspect classes.
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
    public function beforeMethodExecution(MethodInvocation $invocation)
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

### 5. Register the aspect in the aspect kernel

To register the aspect just add an instance of it in the `configureAop()` method of the kernel:

```php
<?php
// app/ApplicationAspectKernel.php

use Aspect\MonitorAspect;

//...

    protected function configureAop(AspectContainer $container)
    {
        $container->registerAspect(new MonitorAspect());
    }

//...
```

### 6. Optional configurations

#### 6.1 Support for weaving Doctrine entities (experimental, alpha)

Weaving Doctrine entities cannot be supported out of the box due to the fact
that Go! AOP generates two sets of classes for each woven entity, a concrete class and
proxy with pointcuts. Doctrine will interpret both of those classes as concrete entities
and assign for both of them the same metadata, which would mess up the database and relations
(see [https://github.com/goaop/framework/issues/327](https://github.com/goaop/framework/issues/327)).

Therefore, a workaround is provided with this library which will sort out
mapping issue in Doctrine. Workaround is in form of event subscriber,
`Go\Bridge\Doctrine\MetadataLoadInterceptor` which has to be registered
when Doctrine is bootstraped in your project. For details how to do that,
see [http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/events.html](http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/events.html).

Event subscriber will modify metadata entity definition for generated Go! Aop proxies
as mapped superclass. That would sort out issues on which you may stumble upon when
weaving Doctrine entities.

### 7. Contribution

To contribute changes, see the [Contribute Readme](CONTRIBUTE.md)
