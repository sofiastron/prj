<?php

namespace App\Service;

use App\Entity\Dish;
use App\Entity\Ingredient;
use App\Entity\Menu;

/** Disponibilité des plats selon les stocks réels. */
class AvailabilityService
{
    public const UNLIMITED = 9999;

    public function __construct(private readonly RecipeResolver $resolver)
    {
    }

    /** Nombre de portions réalisables avec le stock actuel. */
    public function maxPortions(Dish $dish): int
    {
        $max = self::UNLIMITED;
        foreach ($this->resolver->needsForDish($dish) as $need) {
            if (Decimal::isZero($need['quantity'])) {
                continue;
            }
            $portions = Decimal::floorInt(Decimal::div($need['ingredient']->stockQuantity, $need['quantity']));
            $max = min($max, $portions);
        }

        return $max;
    }

    /** Un plat est commandable s'il est à la carte ET si chaque ingrédient est en quantité suffisante. */
    public function isAvailable(Dish $dish): bool
    {
        return $dish->active && $this->maxPortions($dish) >= 1;
    }

    /** @return list<Ingredient> ingrédients en rupture pour une portion de ce plat */
    public function missingIngredients(Dish $dish): array
    {
        $missing = [];
        foreach ($this->resolver->needsForDish($dish) as $need) {
            if (Decimal::cmp($need['ingredient']->stockQuantity, Decimal::round($need['quantity'], 3)) < 0) {
                $missing[] = $need['ingredient'];
            }
        }

        return $missing;
    }

    /** Un menu est commandable si chaque temps propose au moins un plat disponible. */
    public function isMenuAvailable(Menu $menu): bool
    {
        if (!$menu->active || 0 === $menu->courses->count()) {
            return false;
        }
        foreach ($menu->courses as $course) {
            $ok = false;
            foreach ($course->dishes as $dish) {
                if ($this->isAvailable($dish)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return false;
            }
        }

        return true;
    }
}
