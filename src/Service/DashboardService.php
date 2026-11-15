<?php

namespace App\Service;

use App\Entity\Dish;
use App\Entity\Ingredient;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/** Indicateurs du gérant : marges, meilleures ventes, pertes, réapprovisionnement. */
class DashboardService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RecipeResolver $resolver,
    ) {
    }

    /** @return array{orders:int, revenueTtc:string, revenueHt:string, cost:string, grossMargin:string, marginPct:string, lossValue:string, stockValue:string} */
    public function kpis(int $days = 30): array
    {
        $c = $this->em->getConnection();
        $since = $this->since($days);
        $row = $c->fetchAssociative(
            "SELECT COALESCE(SUM(l.quantity*l.unit_price),0) ttc,
                    COALESCE(SUM(l.quantity*l.unit_price/(1+l.vat_rate/100)),0) ht,
                    COALESCE(SUM(l.quantity*l.unit_cost),0) cost
             FROM order_line l WHERE l.status='served' AND l.served_at >= ?",
            [$since],
        );
        $orders = (int) $c->fetchOne("SELECT COUNT(*) FROM customer_order WHERE status='paid' AND paid_at >= ?", [$since]);
        $lossMovements = (string) $c->fetchOne("SELECT COALESCE(SUM(-quantity*unit_cost),0) FROM stock_movement WHERE type='loss' AND created_at >= ?", [$since]);
        $lossWasted = (string) $c->fetchOne('SELECT COALESCE(SUM(quantity*unit_cost),0) FROM order_line WHERE wasted = 1 AND created_at >= ?', [$since]);
        $stockValue = '0';
        foreach ($this->em->getRepository(Ingredient::class)->findAll() as $i) {
            $stockValue = Decimal::add($stockValue, $i->stockValue(), 2);
        }
        $ht = Decimal::round((string) $row['ht'], 2);
        $cost = Decimal::round((string) $row['cost'], 2);
        $margin = Decimal::sub($ht, $cost, 2);

        return [
            'orders' => $orders,
            'revenueTtc' => Decimal::round((string) $row['ttc'], 2),
            'revenueHt' => $ht,
            'cost' => $cost,
            'grossMargin' => $margin,
            'marginPct' => Decimal::isZero($ht) ? '0.0' : Decimal::round(Decimal::mul(Decimal::div($margin, $ht), '100'), 1),
            'lossValue' => Decimal::round(Decimal::add($lossMovements, $lossWasted), 2),
            'stockValue' => $stockValue,
        ];
    }

    /**
     * Marge de chaque plat : théorique (prix HT − coût actuel) et réalisée sur la période (coûts figés à la vente).
     *
     * @return list<array{dish: Dish, cost: string, priceHt: string, margin: string, marginPct: string, sold: int, revenueHt: string, realizedMargin: string}>
     */
    public function dishMargins(int $days = 30): array
    {
        $sold = [];
        foreach ($this->soldByDish($days) as $r) {
            $sold[(int) $r['dish_id']] = $r;
        }
        $out = [];
        foreach ($this->em->getRepository(Dish::class)->findBy([], ['name' => 'ASC']) as $dish) {
            $cost = $this->resolver->costOfDish($dish);
            $ht = $dish->priceExclVat();
            $margin = Decimal::sub($ht, $cost, 2);
            $s = $sold[$dish->id] ?? null;
            $out[] = [
                'dish' => $dish,
                'cost' => Decimal::round($cost, 2),
                'priceHt' => Decimal::round($ht, 2),
                'margin' => $margin,
                'marginPct' => Decimal::isZero($ht) ? '0.0' : Decimal::round(Decimal::mul(Decimal::div($margin, $ht), '100'), 1),
                'sold' => (int) ($s['qty'] ?? 0),
                'revenueHt' => Decimal::round((string) ($s['rev_ht'] ?? '0'), 2),
                'realizedMargin' => Decimal::round(Decimal::sub((string) ($s['rev_ht'] ?? '0'), (string) ($s['cost'] ?? '0')), 2),
            ];
        }

        return $out;
    }

    /** @return list<array{name: string, qty: int, revenue: string}> */
    public function topSellers(int $days = 30, int $limit = 8): array
    {
        $rows = $this->soldByDish($days);
        usort($rows, fn ($a, $b) => (int) $b['qty'] <=> (int) $a['qty']);
        $out = [];
        foreach (\array_slice($rows, 0, $limit) as $r) {
            $out[] = ['name' => (string) $r['name'], 'qty' => (int) $r['qty'], 'revenue' => Decimal::round((string) $r['rev_ttc'], 2)];
        }

        return $out;
    }

    /**
     * Ingrédients à recommander : sous le seuil minimum, ou couverts moins de 2 jours au rythme
     * de consommation des 14 derniers jours. Quantité suggérée = de quoi atteindre le stock cible
     * (ou 7 jours de consommation si c'est plus).
     *
     * @return list<array{ingredient: Ingredient, daily: string, daysLeft: ?string, suggested: string, estimatedCost: string, reason: string}>
     */
    public function restockSuggestions(): array
    {
        $usage = $this->dailyUsage(14);
        $out = [];
        foreach ($this->em->getRepository(Ingredient::class)->findBy([], ['name' => 'ASC']) as $i) {
            $daily = $usage[$i->id] ?? '0';
            $daysLeft = Decimal::isZero($daily) ? null : Decimal::div($i->stockQuantity, $daily, 1);
            $low = $i->isLow() || (Decimal::isZero($i->minStock) && $i->isOut() && !Decimal::isZero($i->targetStock));
            $short = null !== $daysLeft && Decimal::cmp($daysLeft, '2', 1) < 0;
            if (!$low && !$short) {
                continue;
            }
            $toTarget = Decimal::sub($i->targetStock, $i->stockQuantity, 3);
            $toWeek = Decimal::sub(Decimal::mul($daily, '7'), $i->stockQuantity, 3);
            $suggested = Decimal::cmp($toTarget, $toWeek, 3) >= 0 ? $toTarget : $toWeek;
            if (Decimal::cmp($suggested, '0', 3) <= 0) {
                continue;
            }
            $out[] = [
                'ingredient' => $i,
                'daily' => $daily,
                'daysLeft' => $daysLeft,
                'suggested' => Decimal::round($suggested, 3),
                'estimatedCost' => Decimal::round(Decimal::mul($suggested, $i->averageCost), 2),
                'reason' => $low ? 'Sous le seuil minimum' : 'Moins de 2 jours de couverture',
            ];
        }

        return $out;
    }

    /** Consommation moyenne journalière (unité de base) estimée d'après les plats servis. @return array<int,string> */
    private function dailyUsage(int $days): array
    {
        $qtyByDish = [];
        foreach ($this->soldByDish($days) as $r) {
            $qtyByDish[(int) $r['dish_id']] = (int) $r['qty'];
        }
        $usage = [];
        if ([] === $qtyByDish) {
            return $usage;
        }
        foreach ($this->em->getRepository(Dish::class)->findBy(['id' => array_keys($qtyByDish)]) as $dish) {
            foreach ($this->resolver->needsForDish($dish, $qtyByDish[$dish->id]) as $id => $need) {
                $usage[$id] = Decimal::add($usage[$id] ?? '0', Decimal::div($need['quantity'], (string) $days));
            }
        }

        return $usage;
    }

    /** @return list<array<string, mixed>> */
    private function soldByDish(int $days): array
    {
        return $this->em->getConnection()->fetchAllAssociative(
            "SELECT l.dish_id, d.name, SUM(l.quantity) qty, SUM(l.quantity*l.unit_price) rev_ttc,
                    SUM(l.quantity*l.unit_price/(1+l.vat_rate/100)) rev_ht, SUM(l.quantity*l.unit_cost) cost
             FROM order_line l JOIN dish d ON d.id = l.dish_id
             WHERE l.status = 'served' AND l.served_at >= ? GROUP BY l.dish_id, d.name",
            [$this->since($days)],
        );
    }

    private function since(int $days): string
    {
        return (new \DateTimeImmutable("-$days days"))->format('Y-m-d H:i:s');
    }
}
