<?php

namespace App\Exception;

use App\Entity\Ingredient;
use App\Enum\Unit;

class InsufficientStockException extends BusinessRuleViolation
{
    public function __construct(
        public readonly Ingredient $ingredient,
        public readonly string $required,
        public readonly string $available,
    ) {
        parent::__construct(\sprintf(
            'Stock insuffisant pour « %s » : %s requis, %s disponible.',
            $ingredient->name,
            Unit::format($ingredient->dimension, $required),
            Unit::format($ingredient->dimension, $available),
        ));
    }
}
