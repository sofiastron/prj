<?php

namespace App\Entity;

use App\Enum\Unit;

/** Vue commune d'une ligne de recette (de plat ou de préparation). */
interface RecipeLineInterface
{
    public function getIngredient(): ?Ingredient;

    public function getPreparation(): ?Preparation;

    public function getQuantity(): string;

    public function getUnit(): Unit;
}
