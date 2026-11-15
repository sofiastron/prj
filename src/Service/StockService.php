<?php

namespace App\Service;

use App\Entity\Ingredient;
use App\Entity\Inventory;
use App\Entity\OrderLine;
use App\Entity\PurchaseOrder;
use App\Entity\StockMovement;
use App\Entity\User;
use App\Enum\LossReason;
use App\Enum\MovementType;
use App\Enum\Unit;
use App\Exception\BusinessRuleViolation;
use App\Exception\InsufficientStockException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Seul point d'écriture des stocks. Chaque opération :
 *  - s'exécute dans une transaction,
 *  - verrouille les ingrédients concernés (SELECT … FOR UPDATE, dans l'ordre des id pour éviter les deadlocks),
 *  - refuse de rendre un stock négatif,
 *  - laisse une trace dans le journal des mouvements.
 */
class StockService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaction $tx,
    ) {
    }

    /**
     * Vérifie (sous verrou) que les besoins sont couverts, sans rien modifier.
     *
     * @param array<int, array{ingredient: Ingredient, quantity: string}> $needs
     */
    public function assertAvailable(array $needs): void
    {
        $this->tx->run(function () use ($needs) {
            $this->lockAll($needs);
            foreach ($needs as $need) {
                $q = Decimal::round($need['quantity'], 3);
                if (Decimal::cmp($need['ingredient']->stockQuantity, $q) < 0) {
                    throw new InsufficientStockException($need['ingredient'], $q, $need['ingredient']->stockQuantity);
                }
            }
        });
    }

    /**
     * Sortie de stock liée à un plat vendu.
     *
     * @param array<int, array{ingredient: Ingredient, quantity: string}> $needs
     */
    public function consume(array $needs, OrderLine $line, ?User $by = null): void
    {
        $this->tx->run(function () use ($needs, $line, $by) {
            $this->lockAll($needs);
            foreach ($needs as $need) {
                $q = Decimal::round($need['quantity'], 3);
                if (Decimal::cmp($need['ingredient']->stockQuantity, $q) < 0) {
                    throw new InsufficientStockException($need['ingredient'], $q, $need['ingredient']->stockQuantity);
                }
            }
            foreach ($needs as $need) {
                $q = Decimal::round($need['quantity'], 3);
                if (Decimal::isZero($q)) {
                    continue;
                }
                $this->move($need['ingredient'], MovementType::SALE, Decimal::sub('0', $q, 3), $by, orderLine: $line);
            }
        });
    }

    /** Annulation d'un plat non encore lancé : les ingrédients retournent en stock. */
    public function restore(OrderLine $line, ?User $by = null): void
    {
        $this->tx->run(function () use ($line, $by) {
            $movements = $this->em->getRepository(StockMovement::class)->findBy(['orderLine' => $line, 'type' => MovementType::SALE]);
            $already = $this->em->getRepository(StockMovement::class)->count(['orderLine' => $line, 'type' => MovementType::SALE_CANCEL]);
            if ($already > 0) {
                throw new BusinessRuleViolation('Le stock de ce plat a déjà été restitué.');
            }
            $needs = [];
            foreach ($movements as $m) {
                $needs[] = ['ingredient' => $m->ingredient, 'quantity' => $m->quantity];
            }
            $this->lockAll($needs);
            foreach ($movements as $m) {
                $this->move($m->ingredient, MovementType::SALE_CANCEL, Decimal::sub('0', $m->quantity, 3), $by, orderLine: $line);
            }
        });
    }

    /**
     * Réception d'un achat : entrée en stock et recalcul du coût moyen pondéré (CUMP).
     */
    public function receive(PurchaseOrder $purchase, ?User $by = null): void
    {
        $this->tx->run(function () use ($purchase, $by) {
            $needs = [];
            foreach ($purchase->lines as $line) {
                $needs[] = ['ingredient' => $line->ingredient, 'quantity' => '0'];
            }
            $this->lockAll($needs);
            foreach ($purchase->lines as $line) {
                $ingredient = $line->ingredient;
                $qty = Decimal::round($line->unit->toBase($line->quantity), 3);
                $pricePerBase = $line->unit->priceToBase($line->unitPrice);
                $newStock = Decimal::add($ingredient->stockQuantity, $qty, 3);
                if (Decimal::cmp($newStock, '0') > 0) {
                    $value = Decimal::add(
                        Decimal::mul($ingredient->stockQuantity, $ingredient->averageCost),
                        Decimal::mul($qty, $pricePerBase),
                    );
                    $ingredient->averageCost = Decimal::div($value, $newStock);
                }
                $this->move($ingredient, MovementType::PURCHASE, $qty, $by, purchase: $purchase, unitCost: $pricePerBase);
            }
        });
    }

    /** Déclare une perte (casse, péremption, erreur en cuisine…). */
    public function recordLoss(Ingredient $ingredient, string $quantity, Unit $unit, LossReason $reason, ?string $comment = null, ?User $by = null): StockMovement
    {
        if ($unit->dimension() !== $ingredient->dimension) {
            throw new BusinessRuleViolation(\sprintf('Unité « %s » incompatible avec « %s ».', $unit->label(), $ingredient));
        }
        if (Decimal::cmp($quantity, '0') <= 0) {
            throw new BusinessRuleViolation('La quantité perdue doit être strictement positive.');
        }

        return $this->tx->run(function () use ($ingredient, $quantity, $unit, $reason, $comment, $by) {
            $q = Decimal::round($unit->toBase($quantity), 3);
            $this->lockAll([['ingredient' => $ingredient, 'quantity' => $q]]);
            if (Decimal::cmp($ingredient->stockQuantity, $q) < 0) {
                throw new InsufficientStockException($ingredient, $q, $ingredient->stockQuantity);
            }

            return $this->move($ingredient, MovementType::LOSS, Decimal::sub('0', $q, 3), $by, reason: $reason, comment: $comment);
        });
    }

    /**
     * Ajuste le stock à la quantité comptée ; renvoie l'écart (compté − stock courant).
     * Appelé par l'inventaire : le stock courant (sous verrou) fait foi, pas une photo ancienne.
     */
    public function adjustTo(Ingredient $ingredient, string $countedBase, Inventory $inventory, ?User $by = null): string
    {
        if (Decimal::isNegative($countedBase)) {
            throw new BusinessRuleViolation('Une quantité comptée ne peut pas être négative.');
        }

        return $this->tx->run(function () use ($ingredient, $countedBase, $inventory, $by) {
            $this->lockAll([['ingredient' => $ingredient, 'quantity' => '0']]);
            $variance = Decimal::sub(Decimal::round($countedBase, 3), $ingredient->stockQuantity, 3);
            if (!Decimal::isZero($variance)) {
                $this->move($ingredient, MovementType::INVENTORY, $variance, $by, inventory: $inventory);
            }

            return $variance;
        });
    }

    /** @param array<int|string, array{ingredient: Ingredient, quantity: string}> $needs */
    private function lockAll(array $needs): void
    {
        $ingredients = [];
        foreach ($needs as $need) {
            $ingredients[$need['ingredient']->id] = $need['ingredient'];
        }
        ksort($ingredients);
        // Par prudence : écrire avant de relire, car refresh() écraserait des modifications non encore
        // flushées si un appelant enchaînait des opérations sans transaction imbriquée.
        $this->em->flush();
        foreach ($ingredients as $ingredient) {
            // SELECT … FOR UPDATE puis relecture : la valeur en mémoire peut être périmée.
            $this->em->refresh($ingredient, LockMode::PESSIMISTIC_WRITE);
        }
    }

    private function move(
        Ingredient $ingredient,
        MovementType $type,
        string $signedQuantity,
        ?User $by,
        ?OrderLine $orderLine = null,
        ?PurchaseOrder $purchase = null,
        ?Inventory $inventory = null,
        ?LossReason $reason = null,
        ?string $comment = null,
        ?string $unitCost = null,
    ): StockMovement {
        $newStock = Decimal::add($ingredient->stockQuantity, $signedQuantity, 3);
        if (Decimal::isNegative($newStock)) {
            // Dernier rempart : le stock ne devient jamais négatif.
            throw new InsufficientStockException($ingredient, Decimal::sub('0', $signedQuantity, 3), $ingredient->stockQuantity);
        }
        $ingredient->stockQuantity = $newStock;

        $m = new StockMovement();
        $m->ingredient = $ingredient;
        $m->type = $type;
        $m->quantity = $signedQuantity;
        $m->unitCost = $unitCost ?? $ingredient->averageCost;
        $m->lossReason = $reason;
        $m->comment = $comment;
        $m->createdBy = $by;
        $m->orderLine = $orderLine;
        $m->purchaseOrder = $purchase;
        $m->inventory = $inventory;
        $this->em->persist($m);

        return $m;
    }
}
