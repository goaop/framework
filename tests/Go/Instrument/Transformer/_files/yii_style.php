<?php

include($class->method('filename'));
include_once($class->method('filename'));
require($class->method('filename'));
require_once($class->method('filename'));

$app->bindInstallPaths(require __DIR__.'/paths.php');
