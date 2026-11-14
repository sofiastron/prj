<?php

namespace App\Entity;

use App\Enum\Unit;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Ligne de recette d'un plat : un ingrédient OU une préparation de base. */
#[ORM\Entity]
class DishLine implements RecipeLineInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?Dish $dish = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    public ?Ingredient $ingredient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    public ?Preparation $preparation = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    public string $quantity = '0.000';

    #[ORM\Column(length: 5, enumType: Unit::class)]
    public Unit $unit = Unit::G;

    public function getIngredient(): ?Ingredient
    {
        return $this->ingredient;
    }

    public function getPreparation(): ?Preparation
    {
        return $this->preparation;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getUnit(): Unit
    {
        return $this->unit;
    }
}
