<?php

namespace Go\Tests\TestProject\Application;

class Issue293DynamicMembers
{
    public function issue293DynamicPublicMethod()
    {
        echo 'I did something';
    }

    protected function issue293DynamicProtectedMethod()
    {
        echo 'I did something else';
    }
}
