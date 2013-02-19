<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

use Aspect\DebugAspect;
use Aspect\HealthyLiveAspect;

use Go\Core\AspectKernel;
use Go\Core\AspectContainer;

/**
 * Demo Aspect Kernel class
 */
class DemoAspectKernel extends AspectKernel
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
        $container->registerAspect(new DebugAspect('ASPECT!'));
        $container->registerAspect(new HealthyLiveAspect());
    }
}