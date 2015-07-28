Go! Aspect-Oriented Framework for PHP
-----------------

Go! AOP is a modern aspect-oriented framework in plain PHP with rich features for the new level of software development. Framework allows to solve a cross-cutting issues in the traditional object-oriented PHP code by providing a highly efficient and transparent hook system for your exisiting code.

[![Build Status](https://secure.travis-ci.org/goaop/framework.png?branch=master)](https://travis-ci.org/goaop/framework)
[![GitHub release](https://img.shields.io/github/release/goaop/framework.svg)](https://github.com/goaop/framework/releases/latest)
[![Total Downloads](https://img.shields.io/packagist/dt/goaop/framework.svg)](https://packagist.org/packages/goaop/framework)
[![Daily Downloads](https://img.shields.io/packagist/dd/goaop/framework.svg)](https://packagist.org/packages/goaop/framework)
[![SensioLabs Insight](https://img.shields.io/sensiolabs/i/5998393e-29ea-48f8-8e7e-b19e86daa2db.svg)](https://insight.sensiolabs.com/projects/5998393e-29ea-48f8-8e7e-b19e86daa2db)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%205.4-8892BF.svg)](https://php.net/)
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
* Can be integrated with any existing PHP frameworks and libraries.
* Highly optimized for production use: support of opcode cachers, lazy loading of advices and aspects, joinpoints caching, no runtime checks of pointcuts, no runtime annotations parsing, no evals and `__call` methods, no slow proxies and `call_user_func_array()`. Fast bootstraping process (2-20ms) and advice invocation.


What is AOP?
------------

[AOP (Aspect-Oriented Programming)](http://en.wikipedia.org/wiki/Aspect-oriented_programming) is an approach to cross-cutting concerns, where the concerns are designed and implemented
in a "modular" way (that is, with appropriate encapsulation, lack of duplication, etc.), then integrated into all the relevant
execution points in a succinct and robust way, e.g. through declarative or programmatic means.

In AOP terms, the execution points are called join points, a particular set of them is called a pointcut and the new
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

The aim of this framework is to provide easy AOP integration to your application.
Your first step then is to create the `AspectKernel` class
for your application. This class will manage all aspects of your
application in one place.

The framework provides base class to make it easier to create your own kernel.
To create your application kernel extend the abstract class `Go\Core\AspectKernel`

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

Aspect is the key element of AOP philosophy. And Go! AOP framework just uses simple PHP classes for declaring aspects!
Therefore it's possible to use all features of OOP for aspect classes.
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
But we doesn't change any code in the class Example! I can feel you astonishment now )

### 5. Register the aspect in the aspect kernel

To register the aspect just add an instance of it in the `configureAop()` method of the kernel:

``` php
// app/ApplicationAspectKernel.php

use Aspect/MonitorAspect;

//...

    protected function configureAop(AspectContainer $container)
    {
        $container->registerAspect(new MonitorAspect());
    }

//...
```

Now you are ready to use the power of aspects! Feel free to change anything everywhere. If you like this project, you could support it <a href="https://flattr.com/submit/auto?user_id=lisachenko&url=https://github.com/lisachenko/go-aop-php&title=Go!%20AOP%20PHP%20Framework&language=en_GB&tags=aop,php,framework,programming,library"><img align="bottom" alt="Flattr this project!" src="https://api.flattr.com/button/flattr-badge-large.png"></a> [![Gratipay](https://img.shields.io/gratipay/lisachenko.svg)](https://gratipay.com/lisachenko/)

Documentation
-------------

Documentation about Go! library can be found at [official site][1].

[1]: http://go.aopphp.com
