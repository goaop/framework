Go! Aspect-Oriented Framework for PHP
-----------------

Go! AOP is a modern aspect-oriented framework in plain PHP with rich features for the new level of software development. The framework allows cross-cutting issues to be solved in the traditional object-oriented PHP code by providing a highly efficient and transparent hook system for your exisiting code.

[![Build Status](https://secure.travis-ci.org/goaop/framework.png?branch=master)](https://travis-ci.org/goaop/framework)
[![GitHub release](https://img.shields.io/github/release/goaop/framework.svg)](https://github.com/goaop/framework/releases/latest)
[![Total Downloads](https://img.shields.io/packagist/dt/goaop/framework.svg)](https://packagist.org/packages/goaop/framework)
[![Daily Downloads](https://img.shields.io/packagist/dd/goaop/framework.svg)](https://packagist.org/packages/goaop/framework)
[![SensioLabs Insight](https://img.shields.io/sensiolabs/i/9f3e6de1-ea14-4910-b2de-99ff431c9252.svg)](https://insight.sensiolabs.com/projects/9f3e6de1-ea14-4910-b2de-99ff431c9252)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/goaop/framework.svg)](https://packagist.org/packages/goaop/framework)

Features
------------
* Provides dynamic hook system for PHP without changes in the original source code.
* Doesn't require any PECL-extentions (php-aop, runkit, uopz) and DI-containers to work.
* Object-oriented design of aspects, joinpoints and pointcuts.
* Intercepting an execution of any public or protected method in a classes.
* Intercepting an execution of static methods and methods in final classes.
* Intercepting an execution of methods in the traits.
* Intercepting an access to the public/protected properties for objects.
* Hooks for static class initialization (after class is loaded into PHP memory).
* Hooks for object initialization (intercepting `new` keywords).
* Intercepting an invocation of system PHP functions.
* Ability to change the return value of any methods/functions via `Around` type of advice.
* Rich pointcut grammar syntax for defining pointcuts in the source code.
* Native debugging for AOP with XDebug. The code with weaved aspects is fully readable and native. You can put a breakpoint in the original class or in the aspect and it will work (for debug mode)!
* Can be integrated with any existing PHP frameworks and libraries (with or without additional configuration).
* Highly optimized for production use: support of opcode cachers, lazy loading of advices and aspects, joinpoints caching, no runtime checks of pointcuts, no runtime annotations parsing, no evals and `__call` methods, no slow proxies and `call_user_func_array()`. Fast bootstraping process (2-20ms) and advice invocation.


What is AOP?
------------

[AOP (Aspect-Oriented Programming)](http://en.wikipedia.org/wiki/Aspect-oriented_programming) is an approach to cross-cutting concerns, where these concerns are designed and implemented 
in a "modular" way (that is, with appropriate encapsulation, lack of duplication, etc.), then integrated into all the relevant
execution points in a succinct and robust way, e.g. through declarative or programmatic means.

In AOP terms, the execution points are called join points. A set of those points is called a pointcut and the new
behavior that is executed before, after, or "around" a join point is called advice. You can read more about AOP in
[Introduction](http://go.aopphp.com/docs/introduction/) section.


Installation
------------

Go! AOP framework can be installed with composer. Installation is quite easy:

1. Download the framework using composer
2. Create an application aspect kernel
3. Configure the aspect kernel in the front controller
4. Create an aspect
5. Register the aspect in the aspect kernel

### Step 1: Download the library using composer

Ask composer to download the Go! AOP framework with its dependencies by running the command:

``` bash
$ composer require goaop/framework
```

Composer will install the framework to your project's `vendor/goaop/framework` directory.


### Step 2: Create an application aspect kernel

The aim of this framework is to provide easy AOP integration for your application.
You have to first create the `AspectKernel` class
for your application. This class will manage all aspects of your
application in one place.

The framework provides base class to make it easier to create your own kernel.
To create your application kernel, extend the abstract class `Go\Core\AspectKernel`

``` php
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
     *
     * @param AspectContainer $container
     *
     * @return void
     */
    protected function configureAop(AspectContainer $container)
    {
    }
}
```

### 3. Configure the aspect kernel in the front controller

To configure the aspect kernel, call `init()` method of kernel instance.

``` php
// front-controller, for Symfony2 application it's web/app_dev.php

include __DIR__ . '/vendor/autoload.php'; // use composer

// Initialize an application aspect container
$applicationAspectKernel = ApplicationAspectKernel::getInstance();
$applicationAspectKernel->init(array(
        'debug' => true, // use 'false' for production mode
        // Cache directory
        'cacheDir'  => __DIR__ . '/path/to/cache/for/aop',
        // Include paths restricts the directories where aspects should be applied, or empty for all source files
        'includePaths' => array(
            __DIR__ . '/../src/'
        )
));
```

### 4. Create an aspect

Aspect is the key element of AOP philosophy. Go! AOP framework just uses simple PHP classes for declaring aspects, which makes it possible to use all features of OOP for aspect classes.
As an example let's intercept all the methods and display their names:

``` php
// Aspect/MonitorAspect.php

namespace Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\After;
use Go\Lang\Annotation\Before;
use Go\Lang\Annotation\Around;
use Go\Lang\Annotation\Pointcut;

/**
 * Monitor aspect
 */
class MonitorAspect implements Aspect
{

    /**
     * Method that will be called before real method
     *
     * @param MethodInvocation $invocation Invocation
     * @Before("execution(public Example->*(*))")
     */
    public function beforeMethodExecution(MethodInvocation $invocation)
    {
        $obj = $invocation->getThis();
        echo 'Calling Before Interceptor for method: ',
             is_object($obj) ? get_class($obj) : $obj,
             $invocation->getMethod()->isStatic() ? '::' : '->',
             $invocation->getMethod()->getName(),
             '()',
             ' with arguments: ',
             json_encode($invocation->getArguments()),
             "<br>\n";
    }
}
```

Easy, isn't it? We declared here that we want to install a hook before the execution of
all dynamic public methods in the class Example. This is done with the help of annotation
`@Before("execution(public Example->*(*))")`
Hooks can be of any types, you will see them later.
But we don't change any code in the class Example! I can feel your astonishment now.

### 5. Register the aspect in the aspect kernel

To register the aspect just add an instance of it in the `configureAop()` method of the kernel:

``` php
// app/ApplicationAspectKernel.php

use Aspect\MonitorAspect;

//...

    protected function configureAop(AspectContainer $container)
    {
        $container->registerAspect(new MonitorAspect());
    }

//...
```

Now you are ready to use the power of aspects! Feel free to change anything everywhere. If you like this project, you could support it <a href="https://flattr.com/submit/auto?fid=83r77w&url=https%3A%2F%2Fgithub.com%2Fgoaop%2Fframework" target="_blank"><img src="https://button.flattr.com/flattr-badge-large.png" alt="Flattr this" title="Flattr this" border="0"></a> [![Gratipay](https://img.shields.io/gratipay/lisachenko.svg)](https://gratipay.com/lisachenko/)

### 6. Optional configurations

#### 6.1 Custom annotation cache

By default, Go! AOP uses `Doctrine\Common\Cache\FilesystemCache` for caching
annotations. However, if you need to use any other caching engine
for annotation, you may configure cache driver via `annotationCache` configuration
option of your application aspect kernel. Only requirement is
that cache driver implements `Doctrine\Common\Cache\Cache` interface.

This can be very useful when deploying to read-only filesystems. In that
case, you may use, per example, `Doctrine\Common\Cache\ArrayCache` or some
memory-based cache driver.

#### 6.2 Support for weaving Doctrine entities (experimental, alpha)

Weaving Doctrine entities can not be supported out of the box due to the fact
that Go! AOP generates two sets of classes for each weaved entity, a concrete class and
proxy with pointcuts. Doctrine will interpret both of those classes as concrete entities
and assign for both of them same metadata, which would mess up the database and relations
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

To contribute changes see the [Contribute Readme](CONTRIBUTE.md)

Documentation
-------------

Documentation about Go! library can be found at [official site][1].

[1]: http://go.aopphp.com
