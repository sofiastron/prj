<?php

namespace App\Enum;

enum DishCategory: string
{
    case STARTER = 'starter';
    case MAIN = 'main';
    case DESSERT = 'dessert';
    case DRINK = 'drink';

    public function label(): string
    {
        return match ($this) {
            self::STARTER => 'Entrée',
            self::MAIN => 'Plat',
            self::DESSERT => 'Dessert',
            self::DRINK => 'Boisson',
        };
    }
}
