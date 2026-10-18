# goaop/ffi-driver

Experimental weaving driver of the [Go! AOP framework](https://github.com/goaop/framework) that rewires loaded
classes at runtime through [lisachenko/z-engine](https://github.com/lisachenko/z-engine) (PHP FFI) instead of
transforming their sources: classes load natively, the body of every advised method is swapped in place with a
generated dispatcher and the original body stays callable under a private alias.

## Installation

```bash
composer require goaop/ffi-driver
composer require lisachenko/z-engine
```

Requires `ext-ffi` (`ffi.enable=1`) and the opcache JIT off (`opcache.jit=off`, `opcache.jit_buffer_size=0`).

## Usage

```php
use Go\Driver\Ffi\FfiDriver;

ApplicationAspectKernel::getInstance()->init([
    'driver'       => FfiDriver::class,
    'cacheDir'     => __DIR__ . '/../var/cache/aop',
    'includePaths' => [__DIR__ . '/../src'],
]);
```

Method execution and function join points are supported; see
[docs/driver/ffi.md](https://github.com/goaop/framework/blob/master/docs/driver/ffi.md) for the mechanism, the
limits and the development setup.
