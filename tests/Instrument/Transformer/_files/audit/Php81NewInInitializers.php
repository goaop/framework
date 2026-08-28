<?php

declare(strict_types=1);

namespace Repro;

class Collaborator
{
    public function __construct(public string $tag = 'default')
    {
    }
}

class Php81NewInInitializers
{
    public function __construct(
        private Collaborator $service = new Collaborator('ctor-default'),
    ) {
    }

    public function run(Collaborator $helper = new Collaborator('method-default')): string
    {
        static $memo = new \ArrayObject();

        return $this->service->tag . '/' . $helper->tag;
    }
}
