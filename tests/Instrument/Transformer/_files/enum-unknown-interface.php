<?php
declare(strict_types = 1);
namespace Test\ns1 {
    use Missing\Optional\Dependency\Labelled;

    enum EnumWithMissingInterface: string implements \JsonSerializable, Labelled
    {
        case Draft = 'draft';

        public function label(): string
        {
            return ucfirst($this->value);
        }

        public function jsonSerialize(): string
        {
            return $this->value;
        }
    }
}
