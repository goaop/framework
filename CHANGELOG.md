Changelog
======
2.0.0 (May 14, 2016)
* Dropped support for PHP<5.6, clean all old code
* [BC BREAK] Removed ability to rebind closures, because of PHP restrictions, see #247
* [BC BREAK] Removed getDefaultFeatures() method from the AspectKernel, no need in it since PHP5.6
* Migrated from the `Andrewswille/Token-Reflection` to the `goaop/parser-reflection` library for PHP5.6 and PHP7.0 support
* Added support for PHP5.6 and 7.0 features: variadic methods, scalar type hints, return type hints
* [Feature] Command-line tools for debugging aspects and advisors

1.0.0 (Feb 13, 2016)
* Dropped support for PHP<5.5, clean all old code
* Tagged public methods and interfaces with @api tag. No more changes for them in future.
* Refactored core code to use general interceptors for everything instead of separate classes
* New static initialization pointcut to intercept the moment of class loading
* New feature to intercept object initializations, requires INTERCEPT_INITIALIZATIONS to be enabled
* [BC BREAK] remove class() pointcut from the grammar #189
* [BC BREAK] make within() and @within() match all joinpoints #189
* [BC BREAK] drop @annotation syntax. Add @execution pointcut
* Pointcuts can be build now directly from closures via `PointcutBuilder` class
* Do not create files in the cache, if no aspects were applied to them, respects `includePath` option now
* `FilterInjector` is now disabled by default, this job for composer integration now
* Automatic opcache invalidation for cache state file

0.6.1 (Jul 5, 2015)
* Minor patch to fix a bug with overwriting files

0.6.0 (Feb 1, 2015)
* Interceptor for magic methods via "dynamic" pointcut. This feature also gives an access for dynamic pointcuts with different checks and conditions.
* PSR-4 standard for the codebase, thanks to @cordoval
* Added a support for splat (...) operator for more efficient advice invocation (requires PHP5.6)
* New feature system. All tunings of kernel are configured with feature-set. This breaks old configuration option `interceptFunctions=>true` use `'features' => $defaultFeatures | Features::INTERCEPT_FUNCTIONS` now
* Proxy can generate more effective invocation call with `static::class` for PHP>=5.5
* Bug-fixes with empty cache path and PSR4 code, thanks to @andy-shea
* Make pointcut grammar class compatible with PHP7.0

0.5.0 (May 24, 2014)
* Proxies are now stored in the separate files to allow more transparent debugging
* Cache warmer command added
* Extended pointcut syntax for or-ed methods: ClassName->method1|method2(*)
* Access to the annotations for method from MethodInvocation
* Support for read-only file systems (phar, GAE, etc)
* Direct access to advisors (no more serialize/unserialize)
* New @within pointcut to match classes by annotation class
* Nice demo GUI
* Deprecate the usage of submodules for framework
* Inheritance support during class-loading and weaving
* List of small fixes and imrovements

0.4.1 (Aug 27, 2013)
* Better parsing of complex "include" expressions for Yii (by @zvirusz)
* Support for dynamic arguments count for methods by checking for func_get_args() inside method body
* Fixed a bug with autoloaders reodering (by @zvirusz)

0.4.0 (Aug 04, 2013)
* Privileged advices for aspect: allows to access private and protected properties and methods of objects inside advice
* Full integration with composer that allows for easy configuration and workflow with AOP
* Fix some bugs with caching on Windows
* "True" pointcut references that gives the ability to compose a complex pointcut from a simple pointcuts.
* Pointcut now accept "$this" in references to point to the current aspect instance
  (Allows for abstract aspects and abstract pointcuts)
* AspectContainer interface was extracted. This gives the way to integrate with another DIC. Look at Warlock framework.
* Intercepting system functions such as `fopen()`, `file_get_contents()`, etc
* Annotation property pointcut was added
* Ability to declare multiple interfaces and/or traits with single `DeclareParent` introduction
* DeclareError interceptor was added. This can be used for generating an runtime error for methods that should not be executed
  in such a way.

0.3.0 (May 27, 2013)
* Support for dynamic pointcuts: pointcut that match a specific point in the code, if it is under the control
 flow (look at AspectJ cflow and cflowbelow)
* Performance optimizations
* Case-sensitive matching for pointcuts
* Primitive pointcuts (&&, ||, !)
* [BC break] Changes in the kernel configuration (look at the demo for appLoader and autoloadPaths)
* Fix a logic bug for a composite pointcuts

0.2.0 (Mar 15, 2013)
* Intercepting methods in traits
* Pointcut parser/grammar
* Huge pointcuts refactoring, cleaning
* Lazy loading services, pointcuts

0.1.1 (Jan 20, 2013)
* Introduction advice support
* Fix bug with composer autoloader prepending
* Fix doctrine/common dependency: >=2.0.0, <2.4.0

0.1.0 (Jan 08, 2013)
* Initial release of library
