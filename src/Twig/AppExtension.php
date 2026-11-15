<?php

namespace App\Twig;

use App\Entity\Ingredient;
use App\Enum\Dimension;
use App\Enum\Unit;
use App\Service\Decimal;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', fn (string|int|float|null $v) => number_format((float) $v, 2, ',', ' ').' MAD'),
            new TwigFilter('qty', [$this, 'qty']),
            new TwigFilter('num', fn (string|null $v, int $d = 2) => number_format((float) $v, $d, ',', ' ')),
        ];
    }

    /** Quantité en unité de base → texte lisible, selon la dimension (ou l'ingrédient). */
    public function qty(string|null $value, Dimension|Ingredient $of): string
    {
        $dimension = $of instanceof Ingredient ? $of->dimension : $of;

        return Unit::format($dimension, Decimal::round((string) ($value ?? '0'), 3));
    }
}
