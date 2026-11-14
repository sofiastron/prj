<?php

namespace App\Enum;

/** Grandeur physique d'un ingrédient : détermine l'unité de base du stock. */
enum Dimension: string
{
    case MASS = 'mass';
    case VOLUME = 'volume';
    case COUNT = 'count';

    public function baseUnit(): Unit
    {
        return match ($this) {
            self::MASS => Unit::G,
            self::VOLUME => Unit::ML,
            self::COUNT => Unit::PIECE,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MASS => 'Masse (g/kg)',
            self::VOLUME => 'Volume (ml/cl/l)',
            self::COUNT => 'Pièces',
        };
    }

    /** @return list<Unit> */
    public function units(): array
    {
        return array_values(array_filter(Unit::cases(), fn (Unit $u) => $u->dimension() === $this));
    }
}
