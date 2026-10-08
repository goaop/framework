<?php
declare(strict_types = 1);
namespace Test\ns1 {
    use Missing\Optional\Dependency\BaseRule;

    class RuleWithMissingParent extends BaseRule implements \Missing\Optional\Dependency\Rule
    {
        public function getNodeType(): string
        {
            return 'node';
        }
    }
}
