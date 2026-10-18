# src/Aop/Exception — framework exceptions

Read before throwing, adding or re-grouping an exception anywhere in `src/`.

## Rules
- Every exception class lives here and implements `ExceptionInterface` (consumers catch it to handle any Go! AOP failure).
- Throw ONLY through named static constructors: `throw WeavingException::methodReturnsByReference($class, $method)`.
  - The message template lives in the exception class; call sites pass raw values (strings, reflectors, `$previous`), never pre-formatted text.
  - Name states the condition (`cacheDirectoryNotWritable`, `unknownOption`), not the call site.
  - Return type `self`, body `return new self(sprintf(...), previous: $previous)`; formatting helpers (`get_debug_type`, location strings) are private static methods of the exception.
  - Wrapping another throwable → the constructor takes `Throwable $previous` and chains it.
  - Reuse an existing constructor when the condition is the same; add a new one otherwise.
- Never `new XxxException(...)` outside this directory. `tests/Aop/Exception/ExceptionHierarchyTest.php` enforces it
  (single allow-listed exception: `ErrorException` in `CacheWarmer`, converts warnings and is caught in the same loop).
- New exception class → add it to `ExceptionHierarchyTest::exceptionTypes()` with its SPL parent. Leaves are `final`; group bases stay open.

## Groups (pick by the phase that detects the error)
| Phase             | Class                                        | SPL parent                                      | Covers                                                                                   |
|-------------------|----------------------------------------------|-------------------------------------------------|------------------------------------------------------------------------------------------|
| Configuration     | `InvalidConfigurationException`              | `InvalidArgumentException`                      | kernel options, kernel not initialized, cache dir, console input, Doctrine bridge, container registration |
|                   | └ `ContainerException` (final)               | + PSR `ContainerExceptionInterface`             | container entry of the wrong type                                                       |
|                   | `ServiceNotFoundException` (final)           | `OutOfBoundsException` + PSR `NotFoundExceptionInterface` | unknown container id                                                           |
| Aspect definition | `AspectException`                            | `RuntimeException`                              | invalid advice methods/attributes, pointcut reference type, interceptor factory misuse, advisor without advice |
| Parse time        | └ `PointcutSyntaxException` (final)          |                                                 | pointcut lexer/parser errors, invalid patterns                                          |
| Weave time        | `WeavingException`                           | `RuntimeException`                              | source transformation, stream filter, proxy/code generation, cache writes               |
|                   | └ `NotCompilableException` (final)           |                                                 | advisor cache item that can not be compiled to PHP; propagates out of the cache writer  |
| Runtime           | `InvocationException` (final)                | `RuntimeException`                              | joinpoint execution: field access before init, advice lookup, closure scope             |
