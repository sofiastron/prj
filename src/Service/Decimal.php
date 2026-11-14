<?php

namespace App\Service;

/**
 * Arithmétique décimale exacte (bcmath) : évite les erreurs d'arrondi des float
 * sur les quantités de stock et les montants.
 */
final class Decimal
{
    public const SCALE = 6;

    public static function add(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcadd(self::n($a), self::n($b), $scale);
    }

    public static function sub(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcsub(self::n($a), self::n($b), $scale);
    }

    public static function mul(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcmul(self::n($a), self::n($b), $scale);
    }

    public static function div(string $a, string $b, int $scale = self::SCALE): string
    {
        if (self::isZero($b)) {
            throw new \DivisionByZeroError('Division par zéro.');
        }

        return bcdiv(self::n($a), self::n($b), $scale);
    }

    public static function cmp(string $a, string $b, int $scale = self::SCALE): int
    {
        return bccomp(self::n($a), self::n($b), $scale);
    }

    public static function isZero(string $a): bool
    {
        return 0 === bccomp(self::n($a), '0', self::SCALE);
    }

    public static function isNegative(string $a): bool
    {
        return bccomp(self::n($a), '0', self::SCALE) < 0;
    }

    /** Arrondi commercial (demi supérieur, symétrique autour de zéro). */
    public static function round(string $value, int $scale = 2): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';
        $value = self::n($value);

        return bccomp($value, '0', self::SCALE) >= 0
            ? bcadd($value, $half, $scale)
            : bcsub($value, $half, $scale);
    }

    /** Quotient entier par défaut, pour « combien de portions ». */
    public static function floorInt(string $value): int
    {
        return (int) bcadd(self::n($value), '0', 0);
    }

    private static function n(string $v): string
    {
        $v = trim($v);

        return '' === $v || !preg_match('/^-?\d+(\.\d+)?$/', $v) ? '0' : $v;
    }
}
