<?php

namespace App\Service;

use App\Entity\Ingredient;
use App\Entity\PurchaseLine;
use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\PurchaseStatus;
use App\Enum\Unit;
use App\Exception\BusinessRuleViolation;
use Doctrine\ORM\EntityManagerInterface;

class PurchaseService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaction $tx,
        private readonly StockService $stock,
    ) {
    }

    public function create(Supplier $supplier): PurchaseOrder
    {
        $po = new PurchaseOrder();
        $po->supplier = $supplier;
        $this->em->persist($po);
        $this->em->flush();

        return $po;
    }

    public function addLine(PurchaseOrder $po, Ingredient $ingredient, string $quantity, Unit $unit, string $unitPrice): PurchaseLine
    {
        if (PurchaseStatus::DRAFT !== $po->status) {
            throw new BusinessRuleViolation('Seul un bon de commande en brouillon peut être modifié.');
        }
        if ($unit->dimension() !== $ingredient->dimension) {
            throw new BusinessRuleViolation(\sprintf('Unité « %s » incompatible avec « %s ».', $unit->label(), $ingredient));
        }
        if (Decimal::cmp($quantity, '0') <= 0) {
            throw new BusinessRuleViolation('La quantité doit être strictement positive.');
        }
        if (Decimal::isNegative($unitPrice)) {
            throw new BusinessRuleViolation('Le prix ne peut pas être négatif.');
        }
        $line = new PurchaseLine();
        $line->purchaseOrder = $po;
        $line->ingredient = $ingredient;
        $line->quantity = $quantity;
        $line->unit = $unit;
        $line->unitPrice = $unitPrice;
        $po->lines->add($line);
        $this->em->persist($line);
        $this->em->flush();

        return $line;
    }

    public function removeLine(PurchaseOrder $po, PurchaseLine $line): void
    {
        if (PurchaseStatus::DRAFT !== $po->status) {
            throw new BusinessRuleViolation('Seul un bon de commande en brouillon peut être modifié.');
        }
        $po->lines->removeElement($line);
        $this->em->flush();
    }

    public function markOrdered(PurchaseOrder $po): void
    {
        if (PurchaseStatus::DRAFT !== $po->status) {
            throw new BusinessRuleViolation('Ce bon de commande a déjà été envoyé.');
        }
        if ($po->lines->isEmpty()) {
            throw new BusinessRuleViolation('Ajoutez au moins une ligne avant de commander.');
        }
        $po->status = PurchaseStatus::ORDERED;
        $po->orderedAt = new \DateTimeImmutable();
        $this->em->flush();
    }

    /** Réception de la marchandise : stock et coût moyen mis à jour, atomiquement. */
    public function receive(PurchaseOrder $po, ?User $by = null): void
    {
        if (PurchaseStatus::ORDERED !== $po->status) {
            throw new BusinessRuleViolation('Seul un bon de commande « commandé » peut être réceptionné.');
        }
        $this->tx->run(function () use ($po, $by) {
            $this->stock->receive($po, $by);
            $po->status = PurchaseStatus::RECEIVED;
            $po->receivedAt = new \DateTimeImmutable();
        });
    }

    public function cancel(PurchaseOrder $po): void
    {
        if (!\in_array($po->status, [PurchaseStatus::DRAFT, PurchaseStatus::ORDERED], true)) {
            throw new BusinessRuleViolation('Ce bon de commande ne peut plus être annulé.');
        }
        $po->status = PurchaseStatus::CANCELLED;
        $this->em->flush();
    }
}
