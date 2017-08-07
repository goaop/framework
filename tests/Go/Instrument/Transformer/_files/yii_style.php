<?php
declare(strict_types = 1);

include($class->method('filename'));
include_once($class->method('filename'));
require($class->method('filename'));
require_once($class->method('filename'));

$y = $z ? include $x : $v;
include $z ? $x : $y;

$app->bindInstallPaths(require __DIR__.'/paths.php');
