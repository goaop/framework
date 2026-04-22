<?php

declare(strict_types=1);

namespace Go\Stubs;

final class PropertyHookSupport
{
    public string $intercepted = 'intercepted';

    public readonly string $readonly;

    public string $alreadyHooked = 'hooked' {
        get {
            return $this->alreadyHooked;
        }
        set {
            $this->alreadyHooked = $value;
        }
    }

    public function __construct()
    {
        $this->readonly = 'readonly';
    }
}

final class PropertyHookSupportPromoted
{
    public function __construct(
        public string $promoted = 'promoted',
        public readonly string $readonlyPromoted = 'readonly',
        public string $hookedPromoted = 'hooked' {
            get {
                return $value;
            }
            set {
                $value = strtolower($value);
            }
        },
    ) {
    }
}
