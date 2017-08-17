<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

class InconsistentlyWeavedClass
{
    public function badlyWeaved()
    {
        echo 'I get weaved differently every time.';
    }
}
