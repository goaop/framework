<?php

class TestClassTypehint__AopProxied {

    public function publicMethodFixedArguments(Exception $a, $b, $c = null) {}
}

include_once AOP_CACHE_DIR . '/_proxies/Transformer/_files/class-typehint.php/TestClassTypehint.php';
