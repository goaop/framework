Changelog
======
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