<?php

namespace App\Enum;

enum OrderStatus: string
{
    case OPEN = 'open';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Ouverte',
            self::PAID => 'Payée',
            self::CANCELLED => 'Annulée',
        };
    }
}
