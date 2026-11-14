<?php

namespace App\Tests\Unit;

use App\Entity\Dish;
use App\Entity\DishLine;
use App\Entity\Ingredient;
use App\Entity\Preparation;
use App\Entity\PreparationLine;
use App\Enum\Allergen;
use App\Enum\AllergenStatus;
use App\Enum\Dimension;
use App\Enum\Unit;
use App\Exception\IncompatibleUnitException;
use App\Exception\RecipeCycleException;
use App\Service\RecipeResolver;
use PHPUnit\Framework\TestCase;

class RecipeResolverTest extends TestCase
{
    private function ing(string $name, Dimension $d = Dimension::MASS, string $cost = '0'): Ingredient
    {
        $i = new Ingredient();
        $i->name = $name;
        $i->dimension = $d;
        $i->averageCost = $cost;

        return $i;
    }

    private function dishLine(Dish $dish, Ingredient|Preparation $c, string $qty, Unit $unit): void
    {
        $l = new DishLine();
        $l->dish = $dish;
        $l->quantity = $qty;
        $l->unit = $unit;
        $c instanceof Ingredient ? $l->ingredient = $c : $l->preparation = $c;
        $dish->lines->add($l);
    }

    private function prepLine(Preparation $p, Ingredient|Preparation $c, string $qty, Unit $unit): void
    {
        $l = new PreparationLine();
        $l->preparation = $p;
        $l->quantity = $qty;
        $l->unit = $unit;
        $c instanceof Ingredient ? $l->ingredient = $c : $l->subPreparation = $c;
        $p->lines->add($l);
    }

    private function prep(string $name, string $yield, Unit $unit): Preparation
    {
        $p = new Preparation();
        $p->name = $name;
        $p->yieldQuantity = $yield;
        $p->yieldUnit = $unit;

        return $p;
    }

    public function testDirectIngredientsAreConvertedToBaseUnits(): void
    {
        $flour = $this->ing('Farine');
        $oil = $this->ing('Huile', Dimension::VOLUME);
        $dish = new Dish();
        $this->dishLine($dish, $flour, '0.2', Unit::KG);   // 200 g
        $this->dishLine($dish, $oil, '5', Unit::CL);       // 50 ml

        $needs = array_values((new RecipeResolver())->needsForDish($dish));

        $this->assertSame('200', rtrim(rtrim($needs[0]['quantity'], '0'), '.'));
        $this->assertSame('50', rtrim(rtrim($needs[1]['quantity'], '0'), '.'));
    }

    public function testPreparationIsExplodedProportionallyToItsYield(): void
    {
        // Sauce tomate : 2 kg de tomates + 100 ml d'huile → 1,5 kg de sauce.
        $tomato = $this->ing('Tomate');
        $oil = $this->ing('Huile', Dimension::VOLUME);
        $sauce = $this->prep('Sauce tomate', '1.5', Unit::KG);
        $this->prepLine($sauce, $tomato, '2', Unit::KG);
        $this->prepLine($sauce, $oil, '100', Unit::ML);

        // Un plat utilise 300 g de sauce = 1/5 de la préparation.
        $dish = new Dish();
        $this->dishLine($dish, $sauce, '300', Unit::G);

        $needs = array_values((new RecipeResolver())->needsForDish($dish));

        $this->assertSame(0, bccomp($needs[0]['quantity'], '400', 3), 'tomates : 2000 g / 5 = 400 g');
        $this->assertSame(0, bccomp($needs[1]['quantity'], '20', 3), 'huile : 100 ml / 5 = 20 ml');
    }

    public function testNestedPreparationsAndAggregationOfSameIngredient(): void
    {
        $oil = $this->ing('Huile', Dimension::VOLUME);
        $base = $this->prep('Fond', '1', Unit::L);
        $this->prepLine($base, $oil, '100', Unit::ML);
        $sauce = $this->prep('Sauce', '1', Unit::L);
        $this->prepLine($sauce, $base, '500', Unit::ML);   // moitié du fond = 50 ml d'huile
        $this->prepLine($sauce, $oil, '20', Unit::ML);

        $dish = new Dish();
        $this->dishLine($dish, $sauce, '1', Unit::L);
        $this->dishLine($dish, $oil, '10', Unit::ML);

        $needs = array_values((new RecipeResolver())->needsForDish($dish));

        $this->assertCount(1, $needs, 'une seule ligne par ingrédient brut');
        $this->assertSame(0, bccomp($needs[0]['quantity'], '80', 3), '50 + 20 + 10');
    }

    public function testPortionsMultiplyNeeds(): void
    {
        $rice = $this->ing('Riz');
        $dish = new Dish();
        $this->dishLine($dish, $rice, '150', Unit::G);

        $needs = array_values((new RecipeResolver())->needsForDish($dish, 4));

        $this->assertSame(0, bccomp($needs[0]['quantity'], '600', 3));
    }

    public function testCostUsesAverageCostPerBaseUnit(): void
    {
        $meat = $this->ing('Agneau', Dimension::MASS, '0.12');   // 120 MAD/kg
        $dish = new Dish();
        $this->dishLine($dish, $meat, '250', Unit::G);

        $this->assertSame('30.0000', (new RecipeResolver())->costOfDish($dish));
    }

    public function testCycleBetweenPreparationsIsDetected(): void
    {
        $a = $this->prep('A', '1', Unit::KG);
        $b = $this->prep('B', '1', Unit::KG);
        $this->prepLine($a, $b, '100', Unit::G);
        $this->prepLine($b, $a, '100', Unit::G);
        $dish = new Dish();
        $this->dishLine($dish, $a, '100', Unit::G);

        $this->expectException(RecipeCycleException::class);
        (new RecipeResolver())->needsForDish($dish);
    }

    public function testWouldCreateCycleGuardsEditing(): void
    {
        $a = $this->prep('A', '1', Unit::KG);
        $b = $this->prep('B', '1', Unit::KG);
        $c = $this->prep('C', '1', Unit::KG);
        $this->prepLine($a, $b, '100', Unit::G);   // A contient B
        $this->prepLine($b, $c, '100', Unit::G);   // B contient C
        $r = new RecipeResolver();

        $this->assertTrue($r->wouldCreateCycle($c, $a), 'C contenant A fermerait la boucle A→B→C→A');
        $this->assertTrue($r->wouldCreateCycle($a, $a), 'une préparation ne peut pas se contenir');
        $this->assertFalse($r->wouldCreateCycle($a, $c), 'A contenant C directement est sans danger');
    }

    public function testIncompatibleUnitIsRejected(): void
    {
        $milk = $this->ing('Lait', Dimension::VOLUME);
        $dish = new Dish();
        $this->dishLine($dish, $milk, '200', Unit::G);   // des grammes de lait ? non.

        $this->expectException(IncompatibleUnitException::class);
        (new RecipeResolver())->needsForDish($dish);
    }

    public function testPreparationUnitMustMatchItsYieldDimension(): void
    {
        $sauce = $this->prep('Sauce', '1', Unit::L);   // rendement en volume
        $dish = new Dish();
        $this->dishLine($dish, $sauce, '300', Unit::G);   // utilisée en masse

        $this->expectException(IncompatibleUnitException::class);
        (new RecipeResolver())->needsForDish($dish);
    }

    public function testAllergensAggregateThroughPreparations(): void
    {
        $flour = $this->ing('Farine');
        $flour->allergens = [Allergen::GLUTEN->value];
        $flour->allergenStatus = AllergenStatus::KNOWN;
        $butter = $this->ing('Beurre');
        $butter->allergens = [Allergen::MILK->value];
        $butter->traces = [Allergen::NUTS->value, Allergen::MILK->value];
        $butter->allergenStatus = AllergenStatus::KNOWN;
        $dough = $this->prep('Pâte', '1', Unit::KG);
        $this->prepLine($dough, $flour, '600', Unit::G);
        $this->prepLine($dough, $butter, '200', Unit::G);
        $dish = new Dish();
        $this->dishLine($dish, $dough, '300', Unit::G);

        $report = (new RecipeResolver())->allergensOfDish($dish);

        $this->assertSame([Allergen::GLUTEN, Allergen::MILK], $report->allergens);
        $this->assertSame([Allergen::NUTS], $report->traces, 'une trace déjà présente comme allergène n\'est pas répétée');
        $this->assertTrue($report->isComplete());
    }

    public function testUnknownAllergenInformationMakesReportIncomplete(): void
    {
        $known = $this->ing('Riz');
        $known->allergenStatus = AllergenStatus::KNOWN;
        $unknown = $this->ing('Sauce du commerce');   // statut par défaut : UNKNOWN
        $dish = new Dish();
        $this->dishLine($dish, $known, '100', Unit::G);
        $this->dishLine($dish, $unknown, '50', Unit::G);

        $report = (new RecipeResolver())->allergensOfDish($dish);

        $this->assertFalse($report->isComplete());
        $this->assertSame(['Sauce du commerce'], $report->unreliableSources);
    }
}
