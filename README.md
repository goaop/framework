README
======

What is Go?!
-----------------

Go! is a PHP 5.4 library that based on OOP and AOP paradigms.
It allows developers to add a support of AOP to any PHP application.

Requirements
------------

Go! library is only supported on PHP 5.4.0 and up, but it can partially work with PHP 5.3.0 code.

Be warned that PHP versions before 5.4.0 will not work for you, if you will try to use
an aspect for code that use Late Static Binding (LSB) feature.

Go! library will not work with eAccelerator.

Installation
------------

Go! library can be installed with composer or manually with git submodules. Installation is a quick process:

1. Download go-aop-php using composer
2. Create an application aspect kernel
3. Configure the aspect kernel in the front controller
4. Adjust the front controller of your application for proxying autoloading requests to the aspect kernel
5. Create an aspect
6. Register an aspect in the aspect kernel

### Step 1: Download go-aop-php using composer

Add Go! AOP library in your composer.json:

```js
{
    "require": {
        "lisachenko/go-aop-php": "*"
    }
}
```

Now tell composer to download the library with dependencies by running the command:

``` bash
$ php composer.phar update lisachenko/go-aop-php
```

Composer will install the library to your project's `vendor/lisachenko/go-aop-php` directory.

### Step 2: Create an application aspect kernel

The goal of this library is to provide easy AOP integration to your application.
Your first job, then, is to create the `AspectKernel` class
for your application. This class will manage all aspects of your
application in the one place.

The library provides base class to make it easier to create your own kernel.
To create an application kernel extend the abstract class `Go\Core\AspectKernel`

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
     * Returns the path to the application autoloader file, typical autoload.php
     *
     * @return string
     */
    protected function getApplicationLoaderPath()
    {
        return __DIR__ . '/autoload.php';
    }

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

Pay attention to the method `getApplicationLoaderPath()`. It should return the path to the application
loader file, typically `autoload.php` or `bootstrap.php`. Aspect kernel will pass the control to that
file to register an autoloader for application classes.

### 3. Configure the aspect kernel in the front controller

To configure the aspect kernel, your should call init() method of kernel instance.
This configuration is needed to figure out all places in your application structure.

``` php
// front-controller, for Symfony2 application it's web/app_dev.php

// Do not use application autoloader for that files
include __DIR__ . '/../vendor/lisachenko/go-aop-php/src/Go/Core/AspectKernel.php';
include __DIR__ . '/../app/ApplicationAspectKernel.php';

// Initialize an application aspect container
$applicationAspectKernel = ApplicationAspectKernel::getInstance();
$applicationAspectKernel->init(array(
        // Configuration for autoload namespaces
        'autoload' => array(
            'Go'               => realpath(__DIR__ . '/../vendor/lisachenko/go-aop-php/src/'),
            'TokenReflection'  => realpath(__DIR__ . '/../vendor/andrewsville/php-token-reflection/'),
            'Doctrine\\Common' => realpath(__DIR__ . '/../vendor/doctrine/common/lib/')
        ),
        // Application root directory
        'appDir' => __DIR__ . '/../',
        // Cache directory should be disabled for now
        'cacheDir' => null
        // Include paths restricts the directories where aspects should be applied, or empty for all source files
        'includePaths' => array(
            __DIR__ . '/../src/'
        )
));
```

### 4. Adjust the front controller of your application for proxying autoloading requests to the aspect kernel

At this step we have a configured aspect kernel and can try to switch to it instead of default autoloader:

``` php
// front-controller, for Symfony2 application it's web/app_dev.php

// Comment default loader of application at the top of file
// include __DIR__ .'/../vendor/autoload.php'; // for composer
// include __DIR__ .'/../app/autoload.php';  // for old applications
```

### 5. Create an aspect

Aspect is the main part in AOP philosophy. And Go! library just use simple PHP classes for that!
So, it's possible to use all features of OOP for aspect classes.
Just for example, let's show all the methods, that we intercepted:

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

It's simple, isn't it? We described here, that we want to install a hook before the execution of
all dynamic public methods in the class Example. Hooks can be any types, you will see them later.
But we doesn't change any code in the class Example! I can feel you amazement now )

### 6. Register an aspect in the aspect kernel

To register an aspect just add an instance of it in the `configureAop()` method of the kernel:

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

Now you ready to use the power of aspects! Feel free to change anything everywhere.

Documentation
-------------

Documentation about Go! library can be found on [official site][1]. Currently, only Russian language
is supported. If you'd like to contribute, please translate any part of the documentation to your
language.

[1]: http://lisachenko.github.com/go-aop-php/

