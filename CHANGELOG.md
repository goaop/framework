Changelog
======
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