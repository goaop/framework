<?php

declare(strict_types=1);

namespace Go\Stubs;

class PropertyInheritanceParent
{
    public string $parentPublic = 'parent-public';

    protected string $parentProtected = 'parent-protected';

    private string $parentPrivate = 'parent-private';

    final public string $parentFinal = 'parent-final';

    public readonly string $parentReadonly;

    public static string $parentStatic = 'parent-static';

    public string $parentHooked = 'parent-hooked' {
        get {
            return $this->parentHooked;
        }
        set {
            $this->parentHooked = $value;
        }
    }

    public function __construct()
    {
        $this->parentReadonly = 'parent-readonly';
    }
}

final class PropertyInheritanceChild extends PropertyInheritanceParent
{
    public string $childPublic = 'child-public';

    final public string $childFinal = 'child-final';
}
