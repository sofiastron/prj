<?php

namespace App\Service;

use App\Entity\Dish;
use App\Entity\Ingredient;
use App\Entity\Preparation;
use App\Entity\RecipeLineInterface;
use App\Enum\Allergen;
use App\Enum\AllergenStatus;
use App\Exception\IncompatibleUnitException;
use App\Exception\RecipeCycleException;

/**
 * Calcule, à partir d'une recette, les besoins en ingrédients bruts : les préparations
 * de base (sauces, pâtes, fonds…) sont « dépliées » récursivement, proportionnellement
 * à leur rendement. Sert au décompte des stocks, au coût et aux allergènes.
 */
class RecipeResolver
{
    /**
     * Besoins en ingrédients bruts pour N portions d'un plat (quantités en unité de base).
     *
     * @return array<int, array{ingredient: Ingredient, quantity: string}> indexé par id d'ingrédient
     */
    public function needsForDish(Dish $dish, int $portions = 1): array
    {
        $needs = [];
        $this->accumulate($dish->lines, (string) $portions, $needs, []);

        return $needs;
    }

    /**
     * Besoins pour produire une quantité donnée (en unité de base) d'une préparation.
     *
     * @return array<int, array{ingredient: Ingredient, quantity: string}>
     */
    public function needsForPreparation(Preparation $preparation, string $baseQuantity): array
    {
        $needs = [];
        $factor = Decimal::div($baseQuantity, $preparation->yieldUnit->toBase($preparation->yieldQuantity));
        $this->accumulate($preparation->lines, $factor, $needs, [spl_object_id($preparation)]);

        return $needs;
    }

    /** Coût matière d'une portion au CUMP actuel des ingrédients (MAD, 4 décimales). */
    public function costOfDish(Dish $dish): string
    {
        $cost = '0';
        foreach ($this->needsForDish($dish) as $need) {
            $cost = Decimal::add($cost, Decimal::mul($need['quantity'], $need['ingredient']->averageCost));
        }

        return Decimal::round($cost, 4);
    }

    public function allergensOfDish(Dish $dish): AllergenReport
    {
        $present = [];
        $traces = [];
        $unreliable = [];
        foreach ($this->needsForDish($dish) as $need) {
            /** @var Ingredient $ingredient */
            $ingredient = $need['ingredient'];
            if (AllergenStatus::KNOWN !== $ingredient->allergenStatus) {
                $unreliable[] = (string) $ingredient->name;
            }
            foreach ($ingredient->allergens as $code) {
                $present[$code] = Allergen::tryFrom($code);
            }
            foreach ($ingredient->traces as $code) {
                $traces[$code] = Allergen::tryFrom($code);
            }
        }
        $present = array_values(array_filter($present));
        $traces = array_values(array_filter($traces, fn (?Allergen $a) => null !== $a && !\in_array($a, $present, true)));
        usort($present, fn (Allergen $a, Allergen $b) => strcmp($a->label(), $b->label()));
        usort($traces, fn (Allergen $a, Allergen $b) => strcmp($a->label(), $b->label()));

        return new AllergenReport($present, $traces, $unreliable);
    }

    /** Vérifie qu'une ligne de recette est cohérente (un composant, unité compatible, quantité > 0). */
    public function assertLineValid(RecipeLineInterface $line): void
    {
        $ingredient = $line->getIngredient();
        $preparation = $line->getPreparation();
        if ((null === $ingredient) === (null === $preparation)) {
            throw new IncompatibleUnitException('Une ligne de recette doit désigner exactement un ingrédient ou une préparation.');
        }
        if (Decimal::cmp($line->getQuantity(), '0') <= 0) {
            throw new IncompatibleUnitException('La quantité d\'une ligne de recette doit être strictement positive.');
        }
        $expected = $ingredient?->dimension ?? $preparation->yieldUnit->dimension();
        if ($line->getUnit()->dimension() !== $expected) {
            throw new IncompatibleUnitException(\sprintf(
                'Unité « %s » incompatible avec « %s » (attendu : %s).',
                $line->getUnit()->label(),
                $ingredient ?? $preparation,
                implode(', ', array_map(fn ($u) => $u->label(), $expected->units())),
            ));
        }
    }

    /**
     * Une préparation `$owner` peut-elle contenir `$candidate` sans créer de cycle ?
     * (A contient B contient A serait infini.)
     */
    public function wouldCreateCycle(Preparation $owner, Preparation $candidate): bool
    {
        if ($owner === $candidate) {
            return true;
        }
        foreach ($candidate->lines as $line) {
            $sub = $line->subPreparation;
            if (null !== $sub && $this->wouldCreateCycle($owner, $sub)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param iterable<RecipeLineInterface> $lines
     * @param array<int, array{ingredient: Ingredient, quantity: string}> $needs
     * @param list<int> $path identifiants (objets) des préparations en cours de dépliage
     */
    private function accumulate(iterable $lines, string $factor, array &$needs, array $path): void
    {
        foreach ($lines as $line) {
            $this->assertLineValid($line);
            $base = Decimal::mul($line->getUnit()->toBase($line->getQuantity()), $factor);

            if (null !== $ingredient = $line->getIngredient()) {
                $key = $ingredient->id ?? spl_object_id($ingredient);
                $needs[$key] = [
                    'ingredient' => $ingredient,
                    'quantity' => Decimal::add($needs[$key]['quantity'] ?? '0', $base),
                ];
                continue;
            }

            $prep = $line->getPreparation();
            $oid = spl_object_id($prep);
            if (\in_array($oid, $path, true)) {
                throw new RecipeCycleException(\sprintf('Cycle détecté dans les préparations autour de « %s ».', $prep));
            }
            $yield = $prep->yieldUnit->toBase($prep->yieldQuantity);
            $this->accumulate($prep->lines, Decimal::div($base, $yield), $needs, [...$path, $oid]);
        }
    }
}
