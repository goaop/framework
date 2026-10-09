<?php
declare(strict_types=1);

namespace Test\mixed {
    interface MixedContract
    {
        public function run(): string;
    }

    class MixedPlain
    {
    }

    class MixedFirst implements MixedContract
    {
        public function run(): string
        {
            return 'first';
        }
    }

    enum MixedSuit: string
    {
        case Hearts = 'H';

        public function label(): string
        {
            return ucfirst($this->name);
        }
    }

    class MixedChild extends MixedPlain
    {
        public function hello(): string
        {
            return 'child';
        }
    }
}

namespace {
    class MixedGlobal
    {
        public function hello(): string
        {
            return 'global';
        }
    }
}
