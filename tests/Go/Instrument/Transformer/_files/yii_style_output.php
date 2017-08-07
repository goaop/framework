<?php
declare(strict_types = 1);

include \Go\Instrument\Transformer\FilterInjectorTransformer::rewrite(($class->method('filename')), __DIR__);
include_once \Go\Instrument\Transformer\FilterInjectorTransformer::rewrite(($class->method('filename')), __DIR__);
require \Go\Instrument\Transformer\FilterInjectorTransformer::rewrite(($class->method('filename')), __DIR__);
require_once \Go\Instrument\Transformer\FilterInjectorTransformer::rewrite(($class->method('filename')), __DIR__);

$y = $z ? include \Go\Instrument\Transformer\FilterInjectorTransformer::rewrite( $x , __DIR__): $v;
include \Go\Instrument\Transformer\FilterInjectorTransformer::rewrite( $z ? $x : $y, __DIR__);

$app->bindInstallPaths(require \Go\Instrument\Transformer\FilterInjectorTransformer::rewrite( __DIR__.'/paths.php', __DIR__));
