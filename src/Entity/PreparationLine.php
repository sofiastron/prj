<?php

namespace App\Entity;

use App\Enum\Unit;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Ligne de recette d'une préparation : exactement un composant (ingrédient OU sous-préparation). */
#[ORM\Entity]
class PreparationLine implements RecipeLineInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?Preparation $preparation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    public ?Ingredient $ingredient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    public ?Preparation $subPreparation = null;

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
        return $this->subPreparation;
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
