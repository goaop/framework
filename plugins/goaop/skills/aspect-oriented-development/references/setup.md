# Setup, configuration and deployment without a framework bridge

**Check first whether this file applies.** An application on Symfony, Laravel or Yii3 uses the bridge of its
framework, which does everything in "The aspect kernel", "Initializing the kernel" and "Registering aspects" below:
read `frameworks.md` instead. This file is for plain PHP applications, legacy code, libraries and frameworks that have
no bridge yet. The kernel options, features and the deployment principle are the same under a bridge, only spelled in
its configuration.

Full reference: `docs/configuration.md` of the framework (where to find it: `SKILL.md`, "Documentation of the
framework").

## Contents

- [Install](#install)
- [The aspect kernel](#the-aspect-kernel)
- [Initializing the kernel](#initializing-the-kernel)
- [Kernel options](#kernel-options)
- [Features](#features)
- [Registering aspects and their dependencies](#registering-aspects-and-their-dependencies)
- [Tests](#tests)
- [Production deployment](#production-deployment)
- [Console commands](#console-commands)
- [Doctrine entities](#doctrine-entities)

## Install

```bash
composer require goaop/framework
```

Requirements: the PHP version of the package's `composer.json` and `ext-tokenizer`. The console tool `vendor/bin/aspect` also needs `symfony/console`
(`^7.4 || ^8.0`); add it when the project does not have it.

The application classes must be loaded by the Composer autoloader: the framework hooks into it.

## The aspect kernel

One class per application, extending `Go\Core\AspectKernel`. It registers the aspects:

```php
<?php

declare(strict_types=1);

namespace App;

use App\Aspect\LoggingAspect;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

final class ApplicationAspectKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
        $container->addLazyService(LoggingAspect::class, static fn(): LoggingAspect => new LoggingAspect());
    }
}
```

## Initializing the kernel

Put the `init()` call in one file and require it from every entry point, directly after the Composer autoloader and
before any application class is used:

```php
<?php
// config/aop.php

declare(strict_types=1);

use App\ApplicationAspectKernel;

ApplicationAspectKernel::getInstance()->init([
    'debug'        => (bool) ($_SERVER['APP_DEBUG'] ?? false),
    'appDir'       => dirname(__DIR__),
    'cacheDir'     => dirname(__DIR__) . '/var/cache/aop',
    'includePaths' => [dirname(__DIR__) . '/src'],
]);
```

```php
// public/index.php, bin/console, worker scripts, tests/bootstrap.php
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/aop.php';
```

Why the position matters: a class that is loaded before `init()` keeps its original code for the whole process, and
nothing reports it. Entry points are easy to miss; list them (`public/*.php`, `bin/*`, queue workers, cron scripts,
the PHPUnit bootstrap) and check each one. In a framework application the right place is the same: the line after
the autoloader `require` of the front controller and of the console script, before the framework boots.

The kernel is a singleton per process. A second `init()` call does nothing, so the first options win.

## Kernel options

Unknown keys throw `InvalidConfigurationException`.

| Option             | Default | Purpose |
|--------------------|---------|---------|
| `cacheDir`         | required | Directory for the woven code. Must be writable unless the cache is prebuilt. Keep it out of version control. |
| `appDir`           | directory containing `vendor/` | Application root; only files below it are woven. |
| `debug`            | `false` | `true`: changes of sources, aspects and the kernel are picked up; use it in development. `false`: woven classes come from the cache through Composer's class map and edits need a cache rebuild; use it in production. |
| `includePaths`     | `[]` = everything below `appDir` | Directories that are woven. Set it, normally to `src/`: weaving all of `vendor/` is slow and rarely wanted. |
| `excludePaths`     | `[]` | Directories that are never woven. |
| `features`         | `0` | Bitmask of `Go\Aop\Features` flags. |
| `cacheFileMode`    | `0770` minus the umask | Permissions of cache files. |
| `containerClass`   | `Go\Core\Container` | Custom aspect container. |
| `parsedFilesLimit` | `100` | Parsed files kept in memory while weaving. |

### Vendor packages

Keep `vendor/` out of the include paths, with one exception: a dependency that requires `goaop/framework` itself is
written for AOP. It ships aspects, or classes with marker attributes that expect to be advised, and those classes are
only woven when their directory is an include path. Such packages can be found from Composer's metadata, so they are
enabled without listing them by hand:

```php
$includePaths = [__DIR__ . '/../src'];
$vendorDir    = __DIR__ . '/../vendor';
$installed    = json_decode((string) file_get_contents($vendorDir . '/composer/installed.json'), true);
foreach ($installed['packages'] as $package) {
    if (isset($package['require']['goaop/framework']) && !str_starts_with($package['name'], 'goaop/')) {
        $includePaths[] = realpath($vendorDir . '/composer/' . $package['install-path']);
    }
}
```

The `goaop/*` packages themselves (the framework bridges) are skipped: they integrate the framework and contain
nothing to advise. The aspects of an included package still have to be registered in the kernel.

To intercept a class of any other dependency, add exactly its directory instead of opening all of `vendor/`.

## Features

Combine with `|`: `'features' => Features::PREBUILT_CACHE | Features::INTERCEPT_INCLUDES`.

| Flag                 | Effect |
|----------------------|--------|
| `PREBUILT_CACHE`     | Production: trust the cache built at deploy time, never weave or write at runtime. This is the fastest mode: a request checks no file at all (`performance.md`). A class missing from the cache fails the request with a `WeavingException`. Works on read-only file systems. |
| `INTERCEPT_INCLUDES` | Also weave files loaded with `include`/`require` from woven files (legacy code without autoloading). |
| `PARTIAL_REFLECTION` | Weave classes whose parent, interface or trait cannot be located (generated at runtime, `class_alias()`). |

Function and `initialization()` pointcuts need no flag.

## Registering aspects and their dependencies

```php
protected function configureAop(AspectContainer $container): void
{
    // Recommended: the factory runs when an advice of the aspect is called for the first time
    $container->addLazyService(LoggingAspect::class, static fn(): LoggingAspect => new LoggingAspect());

    // Eager alternative, for an aspect without dependencies
    $container->add(MetricsAspect::class, new MetricsAspect());
}
```

Register every aspect under its class name. Weaving reads only the class of an aspect and never creates it, so cache
warm-up works without any service of the application.

Aspect classes can live below `includePaths` (e.g. `src/Aspect`): the weaver skips them, no `excludePaths` entry is
needed.

### Dependencies without an application container

The aspect container holds any service, not only aspects. Build a dependency inside the factory, or register it
once and share it between aspects:

```php
protected function configureAop(AspectContainer $container): void
{
    $container->addLazyService(LoggerInterface::class, static fn(): LoggerInterface => new FileLogger('var/log/app.log'));
    $container->addLazyService(
        LoggingAspect::class,
        static fn(AspectContainer $c): LoggingAspect => new LoggingAspect($c->getService(LoggerInterface::class)),
    );
}
```

A service the application creates itself, after the kernel was initialized, can be handed over at that point. The
aspects are built on their first advice call, so it only has to be there before the advised code runs:

```php
ApplicationAspectKernel::getInstance()->getContainer()->add(LoggerInterface::class, $logger);
```

### Dependencies from the application container

The aspect container is a PSR-11 container that falls back to a delegate for ids it does not know. Attach the
container of the application once it exists; factories of lazy services then resolve from it:

```php
protected function configureAop(AspectContainer $container): void
{
    $container->addLazyService(
        LoggingAspect::class,
        static fn(AspectContainer $c): LoggingAspect => new LoggingAspect($c->getService(LoggerInterface::class)),
    );
}
```

```php
// After the application container was built, e.g. right after the framework kernel booted
ApplicationAspectKernel::getInstance()->getContainer()->setDelegate($psrContainer);
```

- `getService(Foo::class)` returns a typed object and checks its class; `get('some.id')` returns any entry.
- The service must be retrievable from the application container (in Symfony: public, or exposed through a service
  locator).
- An advice that runs before `setDelegate()` and needs a delegated service fails with `ServiceNotFoundException`.
  Typical cause: the pointcut matches code that runs while the framework boots.
- To take the whole aspect from the application container, register a factory under the class name that fetches it by
  the id it has *there*, when that id differs from the class name. A factory must never ask the aspect container for
  its own id.
- An aspect must not depend on a service whose construction it advises: `AspectCircularReferenceException`. Inject
  that dependency lazily or narrow the pointcut.

## Tests

In a framework application the bridge starts the kernel when the application boots, so tests that boot the
application are woven without a bootstrap of your own (`frameworks.md`, "What is the same everywhere"). The rest of
this section is for a kernel of your own.

- **Unit tests of an advice** need no kernel: mock the joinpoint interface (`advices.md`, "Unit-testing an advice").
- **Tests of woven behaviour**: require the same `config/aop.php` from the PHPUnit bootstrap file, after the
  autoloader, with `'debug' => true` and a cache directory of its own (e.g. `var/cache/aop-test`). Without it the
  test suite runs the unwoven classes and tests of aspect behaviour fail or, worse, pass for the wrong reason.
- Mocks: PHPUnit doubles extend the woven class and override its methods, so advices do not run on mocked methods.
  That is usually what a unit test wants.
- Code coverage and static analysis read the original source files; they need no configuration.

## Production deployment

1. Remove the old cache directory.
2. Build the cache. The command fails, and so stops the deployment, when a file cannot be woven:

   ```bash
   vendor/bin/aspect cache:warmup:aop public/index.php
   ```

   `--workers=8` weaves in parallel on large projects.
3. Run with `'debug' => false` and `'features' => Features::PREBUILT_CACHE`.

Rebuild the cache on every deployment. With `debug` off the framework serves woven classes from the cache and does
not notice an edited source file, aspect or pointcut. Without `PREBUILT_CACHE` it still weaves classes the cache does
not know yet on demand, which needs a writable cache directory; with `PREBUILT_CACHE` such a class is an error.

The loader argument is a file that initializes the kernel; it is executed, after Composer's autoloader was loaded
by the command. Give the command a file that only boots (the `config/aop.php` above) if the front controller would
handle a request.

What production mode costs, OPcache settings, preloading, `cache:warmup:opcache` and containers: `performance.md`.

## Console commands

`vendor/bin/aspect` takes the loader file, the file that initializes the kernel, as first argument. In an
application whose own console registers these commands (every bridge does), the kernel is already initialized and
the argument is left out: `bin/console debug:aspect`.

| Command | Purpose |
|---------|---------|
| `vendor/bin/aspect debug:aspect <loader>` | Lists the registered aspects with their pointcuts and advisors. Shows whether an aspect is registered at all. |
| `vendor/bin/aspect debug:advisor <loader>` | Lists every advisor with its expression. |
| `vendor/bin/aspect debug:advisor <loader> --advisor='App\Aspect\X->method'` | Lists the members this advisor matches. |
| `vendor/bin/aspect cache:warmup:aop <loader> [--workers=N] [--fail-fast] [--cache-dir=DIR] [--preload]` | Weaves everything below the include paths into the cache. |
| `vendor/bin/aspect debug:weaving <loader>` | Weaves twice and reports differences, which reveal non-deterministic aspects. |

## Doctrine entities

Doctrine ORM 3.6+ maps woven entities as they are, with two settings:

```php
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Go\Bridge\Doctrine\WovenEntityClassLocator;

// Discover entities through the autoloader; plain directory scanning includes the files unwoven
$configuration->setMetadataDriverImpl(
    new AttributeDriver(WovenEntityClassLocator::createFromDirectories([__DIR__ . '/src/Entity'])),
);
// Needed when properties of entities are intercepted with access(...)
$configuration->enableNativeLazyObjects(true);
```

Doctrine reads and writes raw property values while hydrating and flushing: property advices run for accesses made by
application code only.
