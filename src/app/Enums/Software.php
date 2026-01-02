<?php

namespace App\Enums;

enum Software: string
{
    case Forge = 'forge';
    case Fabric = 'fabric';

    public function label(): string
    {
        return match ($this) {
            self::Forge => 'Forge',
            self::Fabric => 'Fabric',
        };
    }
}
