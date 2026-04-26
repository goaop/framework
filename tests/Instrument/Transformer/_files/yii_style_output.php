<?php
declare(strict_types = 1);

include \Go\Instrument\ClassLoading\AopFileResolver::rewrite(($class->method('filename')), __DIR__);
include_once \Go\Instrument\ClassLoading\AopFileResolver::rewrite(($class->method('filename')), __DIR__);
require \Go\Instrument\ClassLoading\AopFileResolver::rewrite(($class->method('filename')), __DIR__);
require_once \Go\Instrument\ClassLoading\AopFileResolver::rewrite(($class->method('filename')), __DIR__);

$y = $z ? include \Go\Instrument\ClassLoading\AopFileResolver::rewrite($x, __DIR__) : $v;
include \Go\Instrument\ClassLoading\AopFileResolver::rewrite($z ? $x : $y, __DIR__);

$app->bindInstallPaths(require \Go\Instrument\ClassLoading\AopFileResolver::rewrite(__DIR__.'/paths.php', __DIR__));
