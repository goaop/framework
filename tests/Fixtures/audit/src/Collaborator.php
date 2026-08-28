<?php

declare(strict_types=1);

namespace Go\Tests\Audit;

class Collaborator
{
    public function __construct(public string $tag = 'default')
    {
    }
}
