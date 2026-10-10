<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application {
    /**
     * Several woven classes declared in one file, in two namespaces: their proxies share one proxy file (issue #760)
     */
    class MultiClassHolder
    {
        public function hello(): string
        {
            return 'holder';
        }
    }

    /**
     * Not woven, stays as it is
     */
    class MultiClassPlain
    {
        public function greet(): string
        {
            return 'plain';
        }
    }

    /**
     * Woven class extending an unwoven class of the same file
     */
    class MultiClassSecond extends MultiClassPlain
    {
        public function hello(): string
        {
            return 'second:' . $this->greet();
        }
    }
}

namespace Go\Tests\TestProject\Application\Sub {
    /**
     * Woven class of another namespace block of the file
     */
    class MultiClassThird
    {
        public function hello(): string
        {
            return 'third';
        }
    }
}
