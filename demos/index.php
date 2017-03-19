<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
?><!DOCTYPE html>
<html>
<head>
    <title>Go! AOP Demo</title>
    <link href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="//netdna.bootstrapcdn.com/bootswatch/3.1.1/spacelab/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<header class="navbar" id="top" role="banner">>
    <nav class="navbar navbar-default navbar-fixed-top" role="navigation">
        <div class="container-fluid">
          <!-- Brand and toggle get grouped for better mobile display -->
          <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
              <span class="sr-only">Toggle navigation</span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="http://go.aopphp.com/">Go! AOP</a>
          </div>

          <!-- Collect the nav links, forms, and other content for toggling -->
          <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav">
              <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                    <span class="glyphicon glyphicon-tasks"></span>
                    Examples <b class="caret"></b>
                </a>
                <ul class="dropdown-menu">
                  <li><a href="?showcase=loggable">Logging</a></li>
                  <li><a href="?showcase=cacheable">Caching</a></li>
                  <li class="divider">Interceptors</li>
                  <li><a href="?showcase=property-interceptor">Intercepting access to the properties</a></li>
                  <li><a href="?showcase=function-interceptor">Intercepting system functions</a></li>
                  <li><a href="?showcase=dynamic-interceptor">Intercepting magic methods (__call and __callStatic)</a></li>
                  <li class="divider">Advanced</li>
                  <li><a href="?showcase=fluent-interface">Fluent Interface</a></li>
                  <li><a href="?showcase=human-advices">Human live advices</a></li>
                  <li><a href="?showcase=dynamic-traits">Dynamic traits and interfaces</a></li>
                  <li><a href="?showcase=declare-errors">Declare runtime errors</a></li>
                </ul>
              </li>
              <li><a href="http://go.aopphp.com/docs/" target="_blank">Documentation</a></li>
              <li class="dropdown">
                  <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                      AOP Solutions <b class="caret"></b>
                  </a>
                  <ul class="dropdown-menu">
                    <li><a href="https://github.com/lisachenko/php-deal" target="_blank">PhpDeal - Design By Contract Framework</a></li>
                    <li><a href="https://github.com/Codeception/AspectMock" target="_blank">AspectMock - Testing framework</a></li>
                    <li><a href="https://github.com/lisachenko/warlock" target="_blank">Warlock - Go! AOP + Symfony DiC</a></li>
                  </ul>
              </li>
              <li class="dropdown">
                  <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                      Videos & Presentations<b class="caret"></b>
                  </a>
                  <ul class="dropdown-menu">
                    <li><a href="https://www.youtube.com/watch?feature=player_detailpage&v=aZ_9PuHemBk#t=1283" target="_blank">Advanced logging in PHP</a></li>
                    <li><a href="https://www.youtube.com/watch?v=BXKQ99-78bI" target="_blank">Aspect Oriented Programming in PHP</a></li>
                    <li><a href="http://tutsplus.s3.amazonaws.com/tutspremium/Quick-Tips/AspectMock-Is-Pretty-Neat.mp4" target="_blank">AspectMock Is Pretty Neat</a></li>
                    <li class="divider">Slides</li>
                    <li><a href="http://www.slideshare.net/lisachenko/solving-crosscutting-concerns-in-php" target="_blank">Solving Cross-Cutting Concerns in PHP (at DPC16)</a></li>
                    <li><a href="http://www.slideshare.net/lisachenko/weaving-aspects-in-php-with-the-help-of-go-aop-library" target="_blank">Weaving aspects in PHP with the help of Go! AOP library</a></li>
                    <li><a href="http://www.slideshare.net/lisachenko/aspect-oriented-programming-in-php" target="_blank">Aspect-Oriented Programming in PHP (Russian)</a></li>
                  </ul>
              </li>
            </ul>
            <ul class="nav navbar-nav navbar-right">
                <li>AOP:
                    <div class="navbar-form btn-group" data-toggle="button">
                        <?php if (empty($_COOKIE['aop_on']) || $_COOKIE['aop_on'] === 'true'): ?>
                        <button type="button" class="btn btn-info" id="aop_on">On</button>
                        <?php else: ?>
                        <button type="button" class="btn btn-danger active" id="aop_on">Off</button>
                        <?php endif; ?>
                    </div>
                </li>
              <li><a href="https://github.com/lisachenko/go-aop-php" target="_blank">Fork me</a></li>
            </ul>
          </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</header>

<div class="container">

  <div>
    <h1>Welcome</h1>
    <p class="lead">
        This demo shows your an examples of AOP usage. <br>
    </p>
  </div>

  <div class="well"><!--Here will be an output of code execution-->
      <p>
          Please, choose one of available examples from navigation menu. <br>
          You can also try to run this code with XDebug.
      </p>
      <pre>
<?php
/*
 * Start of demo source code here
 */

use Demo\Example\CacheableDemo;
use Demo\Example\DynamicMethodsDemo;
use Demo\Example\ErrorDemo;
use Demo\Example\FunctionDemo;
use Demo\Example\HumanDemo;
use Demo\Example\IntroductionDemo;
use Demo\Example\LoggingDemo;
use Demo\Example\PropertyDemo;
use Demo\Example\UserFluentDemo;
use Demo\Highlighter;
use Go\Instrument\Transformer\MagicConstantTransformer;

$isAOPDisabled = isset($_COOKIE['aop_on']) && $_COOKIE['aop_on'] == 'false';
include __DIR__ . ($isAOPDisabled ? '/../vendor/autoload.php' : '/autoload_aspect.php');

$showCase   = isset($_GET['showcase']) ? $_GET['showcase'] : 'default';
$example    = null;
$aspectName = '';

switch ($showCase) {
    case 'cacheable':
        $aspectName = 'Demo\Aspect\CachingAspect';

        $example = new CacheableDemo();
        $result  = $example->getReport(12345); // First call will take 0.1 second
        echo "Result is: ", $result, PHP_EOL;

        $result = $example->getReport(12346); // This call is cached and result should be '12345'
        echo "Result is: ", $result, PHP_EOL;
        break;

    case 'loggable':
        $aspectName = 'Demo\Aspect\LoggingAspect';

        $example = new LoggingDemo();
        $example->execute('LoggingTask'); // Logging for dynamic methods
        LoggingDemo::runByName('StaticTask'); // Logging for static methods
        break;

    case 'property-interceptor':
        $aspectName = 'Demo\Aspect\PropertyInterceptorAspect';

        $example = new PropertyDemo();
        echo $example->publicProperty; // Read public property
        $example->publicProperty = 987; // Write public property
        $example->showProtected();
        $example->setProtected(987);
        break;

    case 'dynamic-interceptor':
        $aspectName = 'Demo\Aspect\DynamicMethodsAspect';

        $example = new DynamicMethodsDemo();
        $example->saveById(123); // intercept magic dynamic method
        $example->load(456); // notice, that advice for this magic method is not called
        DynamicMethodsDemo::find(array('id' =>124)); //intercept magic static method
        break;

    case 'function-interceptor':
        $aspectName = 'Demo\Aspect\FunctionInterceptorAspect';

        $example = new FunctionDemo();
        $example->testArrayFunctions(array('test' => 1, 'code' => 2, 'more' => 1));
        $example->testFileContent();
        break;

    case 'fluent-interface':
        $aspectName = 'Demo\Aspect\FluentInterfaceAspect';

        $example = new UserFluentDemo(); // Original class doesn't provide fluent interface for us
        if ($example instanceof \Go\Aop\Proxy) { // This check is to prevent fatal errors when AOP is disabled
            $example
                ->setName('John')
                ->setSurname('Doe')
                ->setPassword('root');
        } else {
            echo "Fluent interface is not available without AOP", PHP_EOL;
        }
        break;

    case 'human-advices':
        $aspectName = 'Demo\Aspect\HealthyLiveAspect';

        $example = new HumanDemo();
        echo "Want to eat something, let's have a breakfast!", PHP_EOL;
        $example->eat();
        echo "I should work to earn some money", PHP_EOL;
        $example->work();
        echo "It was a nice day, go to bed", PHP_EOL;
        $example->sleep();
        break;

    case 'dynamic-traits':
        $aspectName = 'Demo\Aspect\IntroductionAspect';

        $example = new IntroductionDemo(); // Original class doesn't implement Serializable
        $example->testSerializable();
        break;

    case 'declare-errors':
        $aspectName = 'Demo\Aspect\DeclareErrorAspect';

        $example = new ErrorDemo();
        $example->oldMethod();
        $example->notSoGoodMethod();
        break;

    default:
}
?>
  </pre></div>
  <div class="panel-group" id="accordion">
<?php // Conditional block with source code of aspect
if ($aspectName):
?>

    <div class="panel panel-default" id="aspect">
      <div class="panel-heading">
          <a data-toggle="collapse" href="#collapseOne">
              Source code of aspect
          </a>
          </div>
      <div class="panel-body well panel-collapse collapse out" id="collapseOne">
<?php
$refAspect = new ReflectionClass($aspectName);
Highlighter::highlight(MagicConstantTransformer::resolveFileName($refAspect->getFileName()));
?>
      </div>
    </div>
<?php // End of conditional block with source code of class
endif;
?>

<?php // Conditional block with source code of class
if ($example):
?>
    <div class="panel panel-default">
      <div class="panel-heading">
          <a data-toggle="collapse" href="#collapseTwo">
             Source code of class
          </a></div>
      <div class="panel-body well panel-collapse collapse out" id="collapseTwo">
<?php
$refObject = new ReflectionObject($example);
Highlighter::highlight(MagicConstantTransformer::resolveFileName($refObject->getFileName()));
?>
      </div>
    </div>
<?php // End of conditional block with source code of class
endif;
?>
  </div><!-- /.accordion -->

</div><!-- /.container -->

    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
    <script>
        $(function() {
            $("#aop_on").click(function() {
                var active=$(this).hasClass('active');
                document.cookie = "aop_on=" + active + "; path=/";
                $(this).toggleClass('btn-info').toggleClass('btn-danger').text(active ? "On" : "Off");
                window.location.reload();
            });
        });
    </script>
</body>
</html>
