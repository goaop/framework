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

    trait MixedFirstOriginalTrait  
    {
        public function run(): string
        {
            return 'first';
        }
    }

    trait MixedSuitOriginalTrait 
    {
        

        public function label(): string
        {
            return ucfirst($this->name);
        }
    }

    trait MixedChildOriginalTrait  
    {
        public function hello(): string
        {
            return 'child';
        }
    }
include_once AOP_CACHE_DIR . '/Transformer/_files/multiple-mixed.php';
}

namespace {
    trait MixedGlobalOriginalTrait
    {
        public function hello(): string
        {
            return 'global';
        }
    }
}
