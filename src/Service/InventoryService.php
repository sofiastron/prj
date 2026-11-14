<?php

namespace App\Service;

use App\Entity\Ingredient;
use App\Entity\Inventory;
use App\Entity\InventoryLine;
use App\Entity\User;
use App\Enum\InventoryStatus;
use App\Enum\Unit;
use App\Exception\BusinessRuleViolation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Inventaire physique : photo du stock théorique, saisie des quantités comptées,
 * puis validation qui calcule les écarts et recale le stock (avec trace au journal).
 */
class InventoryService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaction $tx,
        private readonly StockService $stock,
    ) {
    }

    public function start(?User $by = null): Inventory
    {
        if ($this->em->getRepository(Inventory::class)->count(['status' => InventoryStatus::DRAFT]) > 0) {
            throw new BusinessRuleViolation('Un inventaire est déjà en cours : validez-le ou supprimez-le d\'abord.');
        }

        return $this->tx->run(function () use ($by) {
            $inventory = new Inventory();
            $inventory->createdBy = $by;
            foreach ($this->em->getRepository(Ingredient::class)->findBy([], ['name' => 'ASC']) as $ingredient) {
                $line = new InventoryLine();
                $line->inventory = $inventory;
                $line->ingredient = $ingredient;
                $line->theoreticalQuantity = $ingredient->stockQuantity;
                $inventory->lines->add($line);
            }
            $this->em->persist($inventory);

            return $inventory;
        });
    }

    /** Enregistre une quantité comptée (dans l'unité choisie). Vide = non compté. */
    public function count(InventoryLine $line, ?string $quantity, Unit $unit): void
    {
        if (InventoryStatus::DRAFT !== $line->inventory->status) {
            throw new BusinessRuleViolation('Cet inventaire est déjà validé.');
        }
        if (null === $quantity || '' === trim($quantity)) {
            $line->countedQuantity = null;
            $this->em->flush();

            return;
        }
        if ($unit->dimension() !== $line->ingredient->dimension) {
            throw new BusinessRuleViolation(\sprintf('Unité « %s » incompatible avec « %s ».', $unit->label(), $line->ingredient));
        }
        $quantity = str_replace(',', '.', trim($quantity));
        if (!is_numeric($quantity) || Decimal::isNegative($quantity)) {
            throw new BusinessRuleViolation(\sprintf('Quantité invalide pour « %s ».', $line->ingredient));
        }
        $line->countedQuantity = Decimal::round($unit->toBase($quantity), 3);
        $this->em->flush();
    }

    /**
     * Valide l'inventaire : pour chaque ingrédient compté, l'écart est calculé par rapport
     * au stock courant (relu sous verrou) et le stock est recalé. Les ingrédients non comptés
     * ne sont pas touchés.
     */
    public function validate(Inventory $inventory, ?User $by = null): void
    {
        if (InventoryStatus::DRAFT !== $inventory->status) {
            throw new BusinessRuleViolation('Cet inventaire est déjà validé.');
        }
        $counted = $inventory->lines->filter(fn (InventoryLine $l) => null !== $l->countedQuantity);
        if ($counted->isEmpty()) {
            throw new BusinessRuleViolation('Aucune quantité comptée : rien à valider.');
        }
        $this->tx->run(function () use ($inventory, $by) {
            foreach ($inventory->lines as $line) {
                if (null === $line->countedQuantity) {
                    continue;
                }
                $variance = $this->stock->adjustTo($line->ingredient, $line->countedQuantity, $inventory, $by);
                $line->theoreticalQuantity = Decimal::sub($line->countedQuantity, $variance, 3);
                $line->variance = $variance;
                $line->varianceValue = Decimal::round(Decimal::mul($variance, $line->ingredient->averageCost), 2);
            }
            $inventory->status = InventoryStatus::VALIDATED;
            $inventory->validatedAt = new \DateTimeImmutable();
        });
    }

    public function discard(Inventory $inventory): void
    {
        if (InventoryStatus::DRAFT !== $inventory->status) {
            throw new BusinessRuleViolation('Un inventaire validé ne peut pas être supprimé.');
        }
        $this->em->remove($inventory);
        $this->em->flush();
    }
}
