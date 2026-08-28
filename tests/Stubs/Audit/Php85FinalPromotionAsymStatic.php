<?php

declare(strict_types=1);

namespace Go\Stubs\Audit;

class Php85FinalPromotionAsymStatic
{
    public static private(set) int $instances = 0;

    public function __construct(
        final public string $identity = 'anon',
        public private(set) int $version = 1,
    ) {
        self::$instances++;
    }

    public function describe(): string
    {
        return $this->identity . '#' . $this->version;
    }
}
