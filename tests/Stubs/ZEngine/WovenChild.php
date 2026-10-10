<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

final class WovenChild extends WovenParent
{
    public function own(?self $other = null): string
    {
        return 'own' . ($other === null ? '' : ' with ' . $other::class);
    }
}
