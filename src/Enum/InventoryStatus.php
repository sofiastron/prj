<?php

namespace App\Enum;

enum InventoryStatus: string
{
    case DRAFT = 'draft';
    case VALIDATED = 'validated';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'En cours de comptage',
            self::VALIDATED => 'Validé',
        };
    }
}
