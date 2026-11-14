<?php

namespace App\Enum;

enum MovementType: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case SALE_CANCEL = 'sale_cancel';
    case LOSS = 'loss';
    case INVENTORY = 'inventory';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Achat reçu',
            self::SALE => 'Vente',
            self::SALE_CANCEL => 'Annulation de vente',
            self::LOSS => 'Perte',
            self::INVENTORY => 'Ajustement d\'inventaire',
        };
    }
}
