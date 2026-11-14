<?php

namespace App\Enum;

use App\Service\Decimal;

/** Unités de mesure. Le stock est toujours conservé en unité de base (g, ml, pièce). */
enum Unit: string
{
    case G = 'g';
    case KG = 'kg';
    case ML = 'ml';
    case CL = 'cl';
    case L = 'l';
    case PIECE = 'pce';

    public function dimension(): Dimension
    {
        return match ($this) {
            self::G, self::KG => Dimension::MASS,
            self::ML, self::CL, self::L => Dimension::VOLUME,
            self::PIECE => Dimension::COUNT,
        };
    }

    /** Facteur de conversion vers l'unité de base. */
    public function factor(): string
    {
        return match ($this) {
            self::G, self::ML, self::PIECE => '1',
            self::KG, self::L => '1000',
            self::CL => '10',
        };
    }

    public function label(): string
    {
        return $this->value;
    }

    public function toBase(string $quantity): string
    {
        return Decimal::mul($quantity, $this->factor());
    }

    public function fromBase(string $baseQuantity): string
    {
        return Decimal::div($baseQuantity, $this->factor());
    }

    /** Prix par unité de base à partir d'un prix par unité courante (ex. 80 MAD/kg → 0,08 MAD/g). */
    public function priceToBase(string $pricePerUnit): string
    {
        return Decimal::div($pricePerUnit, $this->factor());
    }

    /** Affichage lisible d'une quantité en unité de base (1500 g → « 1,5 kg »). */
    public static function format(Dimension $dimension, string $baseQuantity): string
    {
        $base = $dimension->baseUnit();
        $big = match ($dimension) {
            Dimension::MASS => self::KG,
            Dimension::VOLUME => self::L,
            Dimension::COUNT => null,
        };
        if (null !== $big && Decimal::cmp(Decimal::mul($baseQuantity, '1'), '1000') >= 0) {
            return self::trim($big->fromBase($baseQuantity)).' '.$big->label();
        }

        return self::trim($baseQuantity).' '.$base->label();
    }

    private static function trim(string $n): string
    {
        $n = Decimal::round($n, 3);

        return str_contains($n, '.') ? rtrim(rtrim($n, '0'), '.') : $n;
    }
}
