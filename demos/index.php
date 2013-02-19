<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

include isset($_GET['original']) ? './autoload.php' : './autoload_aspect.php';

$class = new Example('test');
if ($class instanceof Serializable) {
    echo "Yeah, Example is serializable!", PHP_EOL;
    $ref = new ReflectionClass('Example');
    var_dump($ref->getTraitNames(), $ref->getInterfaceNames());
} else {
    echo "Ooops, Example isn't serializable!", PHP_EOL;
}
unserialize(serialize($class));
$class->publicHello();
for ($i=10; $i--; ) {
    $class->cacheMe(0.2);
}
