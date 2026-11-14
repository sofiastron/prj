<?php

namespace App\Enum;

enum LossReason: string
{
    case BREAKAGE = 'breakage';
    case EXPIRY = 'expiry';
    case KITCHEN_ERROR = 'kitchen_error';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BREAKAGE => 'Casse',
            self::EXPIRY => 'Péremption',
            self::KITCHEN_ERROR => 'Erreur en cuisine',
            self::OTHER => 'Autre',
        };
    }
}
