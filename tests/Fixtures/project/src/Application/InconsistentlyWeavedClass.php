<?php

namespace Go\Tests\TestProject\Application;

class InconsistentlyWeavedClass
{
    public function badlyWeaved()
    {
        echo 'I get weaved differently every time.';
    }
}
