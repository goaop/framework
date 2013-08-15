<?php

include \Go\Instrument\Transformer\FilterInjectorTransformer::rewrite(($class->method('filename')), __DIR__);
include_once \Go\Instrument\Transformer\FilterInjectorTransformer::rewrite(($class->method('filename')), __DIR__);
require \Go\Instrument\Transformer\FilterInjectorTransformer::rewrite(($class->method('filename')), __DIR__);
require_once \Go\Instrument\Transformer\FilterInjectorTransformer::rewrite(($class->method('filename')), __DIR__);