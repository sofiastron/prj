<?php

namespace App\Tests\Integration;

use App\Entity\Supplier;
use App\Entity\StockMovement;
use App\Enum\Dimension;
use App\Enum\InventoryStatus;
use App\Enum\MovementType;
use App\Enum\PurchaseStatus;
use App\Enum\Unit;
use App\Exception\BusinessRuleViolation;
use App\Service\InventoryService;
use App\Service\PurchaseService;

class PurchaseInventoryTest extends IntegrationTestCase
{
    private function supplier(): Supplier
    {
        $s = new Supplier();
        $s->name = 'Fournisseur '.uniqid();
        $this->em->persist($s);
        $this->em->flush();

        return $s;
    }

    public function testReceivingAPurchaseIncreasesStockAndUpdatesWeightedAverageCost(): void
    {
        // 10 kg en stock à 0,05 MAD/g (50 MAD/kg) ; on reçoit 10 kg à 70 MAD/kg → CUMP = 60 MAD/kg.
        $meat = $this->ingredient('Boeuf', Dimension::MASS, '10000', '0.05');
        $purchases = $this->service(PurchaseService::class);
        $po = $purchases->create($this->supplier());
        $purchases->addLine($po, $meat, '10', Unit::KG, '70');
        $purchases->markOrdered($po);

        $purchases->receive($po);

        $this->refresh($meat);
        $this->assertSame('20000.000', $meat->stockQuantity);
        $this->assertSame(0, bccomp($meat->averageCost, '0.06', 6));
        $this->assertSame(PurchaseStatus::RECEIVED, $po->status);
        $this->assertSame(1, $this->em->getRepository(StockMovement::class)->count(['type' => MovementType::PURCHASE]));
    }

    public function testFirstPurchaseOnEmptyStockSetsTheCost(): void
    {
        $oil = $this->ingredient('Huile', Dimension::VOLUME, '0', '0.99');   // ancien coût périmé
        $purchases = $this->service(PurchaseService::class);
        $po = $purchases->create($this->supplier());
        $purchases->addLine($po, $oil, '5', Unit::L, '30');   // 30 MAD/l = 0,03 MAD/ml
        $purchases->markOrdered($po);
        $purchases->receive($po);

        $this->assertSame(0, bccomp($this->refresh($oil)->averageCost, '0.03', 6));
    }

    public function testPurchaseLifecycleIsEnforced(): void
    {
        $oil = $this->ingredient('Huile', Dimension::VOLUME, '0');
        $purchases = $this->service(PurchaseService::class);
        $po = $purchases->create($this->supplier());

        try {
            $purchases->markOrdered($po);   // vide
            $this->fail('bon vide accepté');
        } catch (BusinessRuleViolation) {
        }
        try {
            $purchases->addLine($po, $oil, '5', Unit::KG, '30');   // kg pour de l'huile
            $this->fail('unité incompatible acceptée');
        } catch (BusinessRuleViolation) {
        }
        $purchases->addLine($po, $oil, '5', Unit::L, '30');
        try {
            $purchases->receive($po);   // pas encore commandé
            $this->fail('réception avant commande acceptée');
        } catch (BusinessRuleViolation) {
        }
        $purchases->markOrdered($po);
        $purchases->receive($po);

        $this->expectException(BusinessRuleViolation::class);
        $purchases->receive($po);   // double réception = double stock : interdit
    }

    public function testInventoryComputesVariancesAndAdjustsStock(): void
    {
        $rice = $this->ingredient('Riz', Dimension::MASS, '10000', '0.02');
        $oil = $this->ingredient('Huile', Dimension::VOLUME, '5000', '0.03');
        $salt = $this->ingredient('Sel', Dimension::MASS, '800', '0.01');   // non compté
        $inventories = $this->service(InventoryService::class);

        $inv = $inventories->start();
        foreach ($inv->lines as $line) {
            match ($line->ingredient->name) {
                'Riz' => $inventories->count($line, '9.5', Unit::KG),     // manque 500 g
                'Huile' => $inventories->count($line, '5,2', Unit::L),    // surplus 200 ml (virgule acceptée)
                default => null,
            };
        }
        $inventories->validate($inv);

        $this->assertSame(InventoryStatus::VALIDATED, $inv->status);
        $this->assertSame('9500.000', $this->refresh($rice)->stockQuantity);
        $this->assertSame('5200.000', $this->refresh($oil)->stockQuantity);
        $this->assertSame('800.000', $this->refresh($salt)->stockQuantity, 'un ingrédient non compté n\'est pas touché');
        $byName = [];
        foreach ($inv->lines as $l) {
            $byName[$l->ingredient->name] = $l;
        }
        $this->assertSame('-500.000', $byName['Riz']->variance);
        $this->assertSame('-10.00', $byName['Riz']->varianceValue);
        $this->assertSame('200.000', $byName['Huile']->variance);
        $this->assertNull($byName['Sel']->variance);
        $this->assertSame('-4.00', $inv->totalVarianceValue());
        $this->assertSame(2, $this->em->getRepository(StockMovement::class)->count(['type' => MovementType::INVENTORY]));
    }

    public function testInventoryUsesCurrentStockWhenSalesHappenedDuringCounting(): void
    {
        $rice = $this->ingredient('Riz', Dimension::MASS, '1000');
        $inventories = $this->service(InventoryService::class);
        $inv = $inventories->start();
        $line = $inv->lines->first();
        $inventories->count($line, '900', Unit::G);

        $rice->stockQuantity = '950';   // 50 g consommés pendant le comptage
        $this->em->flush();
        $inventories->validate($inv);

        $this->assertSame('-50.000', $line->variance, 'écart calculé sur le stock courant, pas sur la photo initiale');
        $this->assertSame('900.000', $this->refresh($rice)->stockQuantity);
    }

    public function testInventoryRules(): void
    {
        $rice = $this->ingredient('Riz', Dimension::MASS, '1000');
        $inventories = $this->service(InventoryService::class);
        $inv = $inventories->start();

        try {
            $inventories->start();   // un seul inventaire en cours
            $this->fail('deuxième inventaire accepté');
        } catch (BusinessRuleViolation) {
        }
        try {
            $inventories->count($inv->lines->first(), '-3', Unit::KG);
            $this->fail('quantité négative acceptée');
        } catch (BusinessRuleViolation) {
        }
        try {
            $inventories->validate($inv);   // rien compté
            $this->fail('validation à vide acceptée');
        } catch (BusinessRuleViolation) {
        }
        $inventories->count($inv->lines->first(), '1', Unit::KG);
        $inventories->validate($inv);

        $this->expectException(BusinessRuleViolation::class);
        $inventories->validate($inv);   // déjà validé
    }
}
