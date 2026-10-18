# Configuration and deployment

## Kernel options

Pass the options to `init()` of your aspect kernel, usually in the front controller:

```php
ApplicationAspectKernel::getInstance()->init([
    'debug'        => false,
    'appDir'       => __DIR__ . '/..',
    'cacheDir'     => __DIR__ . '/../var/cache/aop',
    'includePaths' => [__DIR__ . '/../src'],
]);
```

Only the options below are accepted. An unknown key, an unknown `containerClass` or `driver` class or an unknown
feature bit throws `Go\Aop\Exception\InvalidConfigurationException`.

| Option           | Type       | Default                     | Purpose |
|------------------|------------|-----------------------------|---------|
| `cacheDir`       | `string`   | none, required              | Directory for woven sources, proxies, advisor caches and the class map. |
| `appDir`         | `string`   | the directory that contains `vendor/` | Application root. Cache paths mirror source paths relative to it, and only files below it are woven. |
| `debug`          | `bool`     | `false`                     | Development mode: changes of the kernel file and of aspect files invalidate the cache, and every class and `include` is loaded through the weaving stream filter. With `false`, woven classes are loaded from the cache through Composer's class map. Use `false` in production. |
| `includePaths`   | `string[]` | `[]` (everything below `appDir`) | Directories that are woven. Keep it narrow: weaving vendor code is slow and rarely needed. |
| `excludePaths`   | `string[]` | `[]`                        | Directories that are never woven. The cache directory, the framework itself and its runtime dependencies (goaop/dissect, goaop/parser-reflection, nikic/php-parser, psr/container, symfony/finder) are always excluded. |
| `features`       | `int`      | `0`                         | Bitmask of `Go\Aop\Features` flags, see below. |
| `cacheFileMode`  | `int`      | `0770` minus the umask      | Permissions of cache files. Must be between `0600` and `0777` and give the owner read and write access; directories get matching search bits. |
| `containerClass` | `class-string` | `Go\Core\Container`     | Aspect container implementation, must implement `Go\Core\AspectContainer` (a PSR-11 `ContainerInterface`). |
| `driver`         | `class-string<Go\Driver\WeavingDriver>` | `Go\Driver\StreamFilter\StreamFilterDriver::class` | Class of the weaving driver, which decides how advised classes are woven: the built-in stream filter driver, `Go\Driver\StreamWrapper\StreamWrapperDriver` (`goaop/stream-wrapper-driver`) or `Go\Driver\Ffi\FfiDriver` (`goaop/ffi-driver`, experimental). See [docs/driver/](driver/README.md). |

## Features

Combine the flags with `|`, e.g. `'features' => Features::INTERCEPT_INCLUDES | Features::PREBUILT_CACHE`.

| Flag                        | Effect |
|-----------------------------|--------|
| `INTERCEPT_INCLUDES`        | Routes `include`/`require` in woven files through the weaver, for legacy code that is not loaded by Composer. |
| `PREBUILT_CACHE`            | Trusts the cache built at deploy time completely. Skips the cache directory and writability probes, source `filemtime` comparisons, tracked-resource checks and advisor cache freshness checks, and never writes to the cache directory. Rebuilding the cache on every deploy is your responsibility. Works on read-only file systems. |

Function and initialization pointcuts need no flag: the framework enables their machinery from the registered
aspects, lazily registered ones included. An `execution(Namespace\function(*))` pointcut weaves function proxies into
the matching namespaces. An `initialization(...)` pointcut turns on the rewriting of `new Foo(...)` expressions in
woven files; while one is registered, every rewritten `new` goes through the interceptor, so keep such pointcuts to
the code that needs them.

## Aspects from the application container

The aspect container is a PSR-11 container, and it can delegate to the container of your application
(Symfony, Laravel, PHP-DI or any other PSR-11 container): `get()`, `getService()` and `has()` fall back to the
delegate for ids that are not registered in the aspect container. The kernel is initialized before the
application builds its container, so aspects are registered as usual and resolve their dependencies lazily:

```php
protected function configureAop(AspectContainer $container): void
{
    // Runs on the first advice call, when the application container is already attached
    $container->addLazyService(
        MonitorAspect::class,
        static fn(AspectContainer $container) => new MonitorAspect($container->getService(LoggerInterface::class)),
    );
    // or take the whole aspect from the application container, by the id it has there
    // $container->addLazyService(MonitorAspect::class, static fn(AspectContainer $c) => $c->get('app.monitor_aspect'));
}

// Once the application container exists, e.g. right after the framework kernel has booted
ApplicationAspectKernel::getInstance()->getContainer()->setDelegate($psrContainer);
```

Weaving and the cache warm-up only need the class of the aspect, so they run before `setDelegate()`, even in
`bin/aspect`. The factory runs on the first advice call; a dependency that is still missing then throws
`ServiceNotFoundException`.

- A factory never asks the aspect container for its own id: that id resolves to the aspect being built.
  Take the aspect from the application container by the id it has there, or build it from its dependencies.
- A PSR-11 container cannot be introspected: there is no standard way to look services up by interface or by tag
  (some containers can, each in its own way). Aspects are therefore never discovered in the delegate, every aspect
  is registered in `configureAop()`. An integration bridge for a specific framework can collect the services by
  tag or interface from its container and register them with `addLazyService()`.
- An aspect without instance properties cannot be created lazily, so its factory runs when the aspects are
  collected for weaving, before the delegate exists. Such an aspect has no dependencies to inject.

## Extending the transformation pipeline

Every file is transformed by a chain of `Go\Instrument\Transformer\SourceTransformer` services, in registration
order:

1. `SyntaxTreeRewriter` walks the syntax tree of the file once, for all `NodeRewriter` rules. It is a framework
   service, so it always opens the chain.
2. The transformers registered by `AspectKernel::registerTransformerServices()`: `WeavingTransformer`, plus the
   `new` rewriting rule (active while an `initialization(...)` pointcut is registered), the `INTERCEPT_INCLUDES`
   rule and the magic-constant rule.
3. Your own transformers.

To change single syntax nodes, implement `NodeRewriter`. It declares the node classes it handles and rewrites
the tokens of one node at a time. Rules keep no state between nodes; the nodes enclosing the current one are
passed as `$ancestors`, from the outermost to the parent:

```php
use Go\Instrument\Transformer\NodeRewriter;
use Go\Instrument\Transformer\StreamMetaData;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;

final class DebugCallRemover implements NodeRewriter
{
    public array $nodeTypes {
        get => [FuncCall::class];
    }

    public function rewriteNode(Node $node, StreamMetaData $file, array $ancestors): bool
    {
        // Edit $file->tokenStream between $node->getAttribute('startTokenPos') and 'endTokenPos',
        // return true when the tokens were changed
        return false;
    }
}
```

When the nodes of a rule can only occur in a source that contains certain text, implement
`PrefilteredNodeRewriter` instead and list it in the `$sourceMarkers` property, e.g. `['var_dump', 'print_r']` for the
rule above. The rule is skipped for a file without any of the markers, compared case-insensitively, and the syntax
tree is not walked at all when no rule is left.

For whole-file changes, implement `SourceTransformer` and return a `TransformerResult`. Register both kinds as
container services at the top of `configureAop()`. The chain and its rules are assembled on the first cache
miss, and loading a class can already cause that miss:

```php
protected function configureAop(AspectContainer $container): void
{
    $container->addLazyService(DebugCallRemover::class, fn() => new DebugCallRemover());
    $container->addLazyService(MyTransformer::class, fn() => new MyTransformer());
    // ...aspects
}
```

Override `registerTransformerServices()` to replace, omit or reorder the built-in transformers and rules, for
example to swap `WeavingTransformer` for your own weaver. The `SyntaxTreeRewriter` walk and any rules you register
stay in place.

## Console

`bin/aspect` needs `symfony/console` (`^7.4 || ^8.0`). Every command takes the path to the file that initializes your kernel, usually the front controller. That file is executed.

| Command                                   | Purpose |
|-------------------------------------------|---------|
| `cache:warmup:aop <loader> [--fail-fast]` | Weaves every file below the include paths into the cache. Fails when a file cannot be woven and writes the errors to stderr. |
| `debug:aspect <loader> [--aspect=...]`    | Lists the registered aspects and their advices. |
| `debug:advisor <loader> [--advisor=...]`  | Lists the advisors; with `--advisor` shows the joinpoints that advisor matches. |
| `debug:weaving <loader>`                  | Warms up the cache twice and reports proxies that are woven differently, which points to non-deterministic aspects. |

## Deployment

1. Clear the cache directory.
2. Warm up the cache: `vendor/bin/aspect cache:warmup:aop public/index.php`. The command fails when a file cannot be woven, so a deploy script stops instead of shipping an incomplete cache.
3. Run with `'debug' => false` and `Features::PREBUILT_CACHE`.

Rebuild the cache whenever the code or the aspects change. When upgrading from 3.x, see the [upgrade guide](../UPGRADE-4.0.md).
