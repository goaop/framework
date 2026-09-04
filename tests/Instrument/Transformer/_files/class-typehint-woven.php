<?php

trait TestClassTypehintOriginalTrait {

    public function publicMethodFixedArguments(Exception $a, $b, $c = null) {}
}

include_once AOP_CACHE_DIR . '/Transformer/_files/class-typehint.php';
