<?php

namespace App\Enum;

enum PurchaseStatus: string
{
    case DRAFT = 'draft';
    case ORDERED = 'ordered';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::ORDERED => 'Commandé',
            self::RECEIVED => 'Reçu',
            self::CANCELLED => 'Annulé',
        };
    }
}
