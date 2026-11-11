# Framework integration

An application that runs on a framework gets Go! AOP through a bridge package. The bridge starts the aspect kernel
early enough, registers aspects from the service container of the framework, attaches that container so aspects get
their dependencies injected, adds the console commands and hooks the cache warm-up into the deployment commands of the
framework. Use it instead of an `AspectKernel` of your own: it encapsulates integration problems that are hard to see
from the outside (classes loaded before the kernel, container dumps that bypass the class loader, preloading).

Each bridge has a README with the full reference, in `vendor/goaop/<package>/README.md` once installed. Read it
before changing configuration: it is the source of truth for that version.

## Contents

- [Which bridge](#which-bridge)
- [Symfony](#symfony)
- [Laravel](#laravel)
- [Yii3](#yii3)
- [What is the same everywhere](#what-is-the-same-everywhere)
- [A framework without a bridge](#a-framework-without-a-bridge)

## Which bridge

| Framework | Package | Example application |
|-----------|---------|---------------------|
| Symfony 7.4 / 8 | [`goaop/goaop-symfony-bundle`](https://github.com/goaop/goaop-symfony-bundle) | [goaop/shop-demo-symfony](https://github.com/goaop/shop-demo-symfony) |
| Laravel 12 / 13 | [`goaop/goaop-laravel-bridge`](https://github.com/goaop/goaop-laravel-bridge) | [goaop/shop-demo-laravel](https://github.com/goaop/shop-demo-laravel) |
| Yii3 | [`goaop/goaop-yii3-bridge`](https://github.com/goaop/goaop-yii3-bridge) | [goaop/shop-demo-yii3](https://github.com/goaop/shop-demo-yii3) |
| None (plain PHP) | `goaop/framework` alone, see `setup.md` | [goaop/shop-demo](https://github.com/goaop/shop-demo) |

The shop demos implement logging, caching, transactions, monitoring and property interception as aspects in each
framework, with benchmarks and a production Docker image. Look there for an idiomatic layout before inventing one.

The supported framework versions are those of the bridge's `composer.json` at the time of writing; check the
installed one.

| | Symfony | Laravel | Yii3 |
|---|---|---|---|
| Kernel starts | when the bundle boots, as first bundle | at the start of the application's boot phase | at the `AopBootstrap::init()` line of the entry script |
| Aspects are found by | autoconfiguration of `Go\Aop\Aspect` services, or the `goaop.aspect` tag | the `go_aop.aspects` config list, or the `goaop.aspect` container tag | the `aspects` parameter, or an opt-in container tag |
| Configuration | `config/packages/go_aop.yaml` | `config/go_aop.php`, env variables | `params['goaop/goaop-yii3-bridge']` |
| Default include path | `src/` | `app/` | `@src` |
| Default cache directory | `%kernel.cache_dir%/aop` | `storage/framework/aop` | `@runtime/aop` |
| Build the cache | `bin/console cache:clear` or `cache:warmup` | `php artisan aop:warmup` | `./yii aop:warmup` |
| Check a pointcut | `bin/console debug:advisor --advisor=...` | read the woven class, see `troubleshooting.md` | `./yii aop:debug:advisor --advisor=...` |

## Symfony

```bash
composer require goaop/goaop-symfony-bundle
```

Register the bundle as the **first** entry of `config/bundles.php`. Symfony Flex appends it at the end, so the
`cache:clear` at the end of `composer require` fails once with "GoAopBundle must be the first bundle": move the line
up and run `bin/console cache:clear` again.

```php
// config/bundles.php
return [
    Go\Symfony\GoAopBundle\GoAopBundle::class => ['all' => true],
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    // ...
];
```

```yaml
# config/packages/go_aop.yaml (optional: these are the defaults plus the production feature)
go_aop:
    include_paths: ['%kernel.project_dir%/src']

when@prod:
    go_aop:
        features: [PREBUILT_CACHE]
```

- **Aspects** are ordinary services. A class implementing `Go\Aop\Aspect` is registered by autoconfiguration and gets
  its dependencies by autowiring; there is no kernel class to edit. Without autoconfiguration, tag the service with
  `goaop.aspect`.
- **Mode** follows `%kernel.debug%`.
- **Commands** are registered in `bin/console` and take no loader argument: `debug:aspect`, `debug:advisor`,
  `debug:weaving`, `cache:warmup:aop`, `cache:warmup:opcache`.
- **Deployment** needs no extra step: `cache:clear` and `cache:warmup` weave the application through a cache warmer
  and fail when a file cannot be woven.
- **Web profiler**: the Go! AOP panel lists the aspects, the woven classes of the request and the classes that were
  loaded before weaving was possible, which answers most "my aspect does not apply" questions.
- **Doctrine** is configured by the bundle when DoctrineBundle is registered.
- **Circular reference with an aspect**: inject the dependency lazily, e.g. `#[Autowire(lazy: true)]`.
- **Preloading**: require the `_preload.php` of the AOP cache in `config/preload.php` before the preload file of
  the container; do not boot the kernel there.

## Laravel

```bash
composer require goaop/goaop-laravel-bridge
```

The service provider is discovered automatically. Publish the configuration to change it:

```bash
php artisan vendor:publish --tag=goaop-config
```

```php
// config/go_aop.php
'aspects' => [
    App\Aspects\LoggingAspect::class,
],
```

- **Aspects** are listed in `go_aop.aspects` and resolved through the Laravel container on the first call of one of
  their advices, so constructor injection works. A service tagged `goaop.aspect` is picked up as well; prefer the
  list for aspects with heavy constructors, because tagged services are resolved when the provider boots.
- **Mode** follows `GOAOP_DEBUG`, then `APP_DEBUG`. Features are a bitmask in `GOAOP_FEATURES` (`PREBUILT_CACHE` is
  64).
- **Commands**: `php artisan aop:warmup` (`--workers=N`, `--preload`, `--cache-dir=DIR`) and
  `php artisan aop:warmup-opcache`. There is no command that lists the matches of a pointcut: run the code in debug
  mode and read the woven class.
- **Deployment order** with `PREBUILT_CACHE`: `composer install --no-dev --optimize-autoloader`, then
  `php artisan optimize`, then `php artisan aop:warmup`.
- **Not woven**: classes loaded before the application boots, and vendor code. Exclude the exception handler with
  `excludePaths`.

## Yii3

```bash
composer require goaop/goaop-yii3-bridge
```

Add one line to every entry script (`public/index.php`, `yii`, the bootstrap of functional tests), between the
creation of the runner and its run:

```php
use Go\Yii3\GoAopBridge\AopBootstrap;

$runner = new HttpApplicationRunner(/* ... */);

AopBootstrap::init($runner->getConfig(), rootPath: dirname(__DIR__));

$runner->run();
```

Without the line the kernel still starts from the `bootstrap` config group, but late: classes loaded while the
container was built are not woven, and in debug mode that is every class with an array definition.

```php
// config/common/params.php
'goaop/goaop-yii3-bridge' => [
    'aspects' => [
        App\Aspect\LoggingAspect::class,
    ],
],
```

- **Aspects** are regular services of the Yii container, listed in the `aspects` parameter and created on the first
  call of one of their advices.
- **Mode** follows the `debug` parameter, then `GOAOP_DEBUG`, then `APP_DEBUG`.
- **Commands** (with `yiisoft/yii-console`): `./yii aop:warmup`, `./yii aop:opcache`, `./yii aop:debug:aspect`,
  `./yii aop:debug:advisor`, `./yii aop:debug:weaving`. They take no loader argument.
- **Deployment**: `./yii aop:warmup --workers=4`. Keep the cache out of persistent volumes: with the default
  `@runtime/aop` and a volume on `runtime/`, new containers serve the cache of the previous release. Set
  `GOAOP_CACHE_DIR` to a directory of the image.
- **Not woven**: classes the entry script uses before the `AopBootstrap::init()` line, and classes the configuration
  loads while that line reads it.

## What is the same everywhere

Everything else in this skill applies unchanged: pointcuts, advices, joinpoints, recipes, the cache layout, the
limitations. The kernel options keep their meaning under the spelling of the framework (`include_paths`,
`includePaths`). Where this skill shows `configureAop()` or `init([...])`, a bridge user changes the configuration of
the bridge instead.

Three things to keep in mind in any framework:

- **Tests.** Aspects apply in tests that boot the application (functional and integration tests), because booting
  starts the kernel. A unit test that never boots the application runs the original classes: test the advice itself
  there, with a mocked joinpoint (`advices.md`).
- **Long-running workers** (RoadRunner, FrankenPHP worker mode, Swoole) initialize the kernel once per worker; aspect
  instances live across requests, so per-request state in an aspect has to be reset like in any shared service.
- **The cache belongs to the release.** Build it with the deployment and never share it between releases.

## A framework without a bridge

When the application runs on a framework that has no bridge (Laminas, CakePHP, Slim, Spiral, a company framework),
the integration belongs in a reusable package, not in the application:

1. **Tell the user** that no bridge exists for the framework and that the right fix is a bridge package, and propose
   to open a proposal for it in the [issue tracker of the framework](https://github.com/goaop/framework/issues): the
   maintainers can provide a repository in the `goaop` organization (named like the others,
   `goaop-<framework>-bridge`), where the bridge is developed and released for everybody. Do not open the issue
   without the user's consent.
2. **Design the package after the existing bridges.** It needs, in this order of importance:
   - a kernel class extending `Go\Core\AspectKernel` that is initialized at the earliest point the framework offers,
     before the framework loads application classes;
   - kernel options built from the configuration system of the framework (`debug` from its debug flag, `cacheDir`
     below its cache directory, `includePaths` on its source directory);
   - aspects taken from the service container: registered with `addLazyService()` under their class name, with a
     factory that fetches the service from the container, and the container attached with `setDelegate()`;
   - the console commands of the framework (`Go\Console\Command\*`) registered in the console of the application,
     where they need no loader argument;
   - the cache warm-up hooked into the cache or deployment command of the framework.
3. **Until the bridge exists**, the application can use a kernel of its own as described in `setup.md`. Keep that
   code in one place (a kernel class and one bootstrap file), so that it is easy to move into the bridge.

Read the source of the bridge closest to the framework at hand before writing a new one; the Yii3 bridge is the
smallest and has the lowest overhead, the Symfony bundle covers the most integration problems.
