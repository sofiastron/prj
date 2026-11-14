<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\DiningTable;
use App\Entity\Dish;
use App\Entity\Menu;
use App\Entity\OrderLine;
use App\Entity\OrderMenu;
use App\Entity\User;
use App\Enum\LineStatus;
use App\Enum\OrderStatus;
use App\Exception\BusinessRuleViolation;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Prise de commande et cycle de vie des plats.
 *
 * Règle de décompte : les ingrédients sortent du stock dès que le plat est envoyé en cuisine
 * (création de la ligne). Le stock affiché est donc toujours le stock réellement disponible,
 * ce qui évite de vendre deux fois le dernier ingrédient. Annuler un plat PENDING restitue
 * le stock ; l'annuler après lancement le déclare perdu.
 */
class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaction $tx,
        private readonly RecipeResolver $resolver,
        private readonly StockService $stock,
    ) {
    }

    public function open(DiningTable $table, User $server, int $covers = 1): CustomerOrder
    {
        if ($covers < 1) {
            throw new BusinessRuleViolation('Il faut au moins un couvert.');
        }

        return $this->tx->run(function () use ($table, $server, $covers) {
            // Verrou sur la table : deux serveurs ne peuvent pas ouvrir la même table en même temps.
            $this->em->refresh($table, LockMode::PESSIMISTIC_WRITE);
            if (null !== $this->openOrderFor($table)) {
                throw new BusinessRuleViolation(\sprintf('La table « %s » a déjà une commande ouverte.', $table));
            }
            $order = new CustomerOrder();
            $order->table = $table;
            $order->server = $server;
            $order->covers = $covers;
            $this->em->persist($order);

            return $order;
        });
    }

    public function openOrderFor(DiningTable $table): ?CustomerOrder
    {
        return $this->em->getRepository(CustomerOrder::class)->findOneBy(['table' => $table, 'status' => OrderStatus::OPEN]);
    }

    public function addDish(CustomerOrder $order, Dish $dish, int $quantity = 1, ?string $note = null, ?User $by = null): OrderLine
    {
        $this->assertOpen($order);
        if ($quantity < 1 || $quantity > 50) {
            throw new BusinessRuleViolation('La quantité doit être comprise entre 1 et 50.');
        }

        return $this->tx->run(function () use ($order, $dish, $quantity, $note, $by) {
            $this->assertOnCard($dish);
            $line = $this->buildLine($order, $dish, $quantity, $dish->price, $note);
            // Lève InsufficientStockException si un ingrédient manque : rien n'est alors enregistré.
            $this->stock->consume($this->resolver->needsForDish($dish, $quantity), $line, $by);

            return $line;
        });
    }

    /**
     * Commande d'un menu : un plat choisi par « temps ». Tout ou rien : si un seul plat
     * est indisponible, la formule entière est refusée.
     *
     * @param array<int, int> $choices identifiant du temps (MenuCourse) => identifiant du plat choisi
     */
    public function addMenu(CustomerOrder $order, Menu $menu, array $choices, ?User $by = null): OrderMenu
    {
        $this->assertOpen($order);
        if (!$menu->active) {
            throw new BusinessRuleViolation(\sprintf('Le menu « %s » n\'est plus proposé.', $menu));
        }

        return $this->tx->run(function () use ($order, $menu, $choices, $by) {
            $picked = [];
            foreach ($menu->courses as $course) {
                $dishId = $choices[$course->id] ?? null;
                $dish = null;
                foreach ($course->dishes as $candidate) {
                    if ($candidate->id === (int) $dishId) {
                        $dish = $candidate;
                    }
                }
                if (null === $dish) {
                    throw new BusinessRuleViolation(\sprintf('Choisissez un plat pour « %s ».', $course->label));
                }
                $this->assertOnCard($dish);
                $picked[] = $dish;
            }
            if ([] === $picked) {
                throw new BusinessRuleViolation('Ce menu ne contient aucun temps.');
            }

            // Vérification globale d'abord : on ne consomme rien si la formule est incomplète.
            $total = [];
            foreach ($picked as $dish) {
                foreach ($this->resolver->needsForDish($dish) as $key => $need) {
                    $total[$key] = [
                        'ingredient' => $need['ingredient'],
                        'quantity' => Decimal::add($total[$key]['quantity'] ?? '0', $need['quantity']),
                    ];
                }
            }
            $this->stock->assertAvailable($total);

            $orderMenu = new OrderMenu();
            $orderMenu->order = $order;
            $orderMenu->menu = $menu;
            $orderMenu->menuName = (string) $menu->name;
            $orderMenu->price = $menu->price;
            $order->menus->add($orderMenu);
            $this->em->persist($orderMenu);

            // Répartition du prix de la formule au prorata des prix à la carte (le dernier plat
            // absorbe l'arrondi) : les statistiques par plat restent justes.
            $listSum = '0';
            foreach ($picked as $dish) {
                $listSum = Decimal::add($listSum, $dish->price, 2);
            }
            $allocated = '0';
            foreach ($picked as $i => $dish) {
                if ($i === \count($picked) - 1) {
                    $share = Decimal::sub($menu->price, $allocated, 2);
                } elseif (Decimal::isZero($listSum)) {
                    $share = Decimal::round(Decimal::div($menu->price, (string) \count($picked)), 2);
                } else {
                    $share = Decimal::round(Decimal::mul($menu->price, Decimal::div($dish->price, $listSum)), 2);
                }
                $allocated = Decimal::add($allocated, $share, 2);
                $line = $this->buildLine($order, $dish, 1, $share, null);
                $line->orderMenu = $orderMenu;
                $this->stock->consume($this->resolver->needsForDish($dish), $line, $by);
            }

            return $orderMenu;
        });
    }

    /** Fait avancer un plat : PENDING → PREPARING → READY → SERVED. */
    public function advance(OrderLine $line, LineStatus $to): void
    {
        $this->assertOpen($line->order);
        if (!$line->status->canTransitionTo($to) || LineStatus::CANCELLED === $to) {
            throw new BusinessRuleViolation(\sprintf('Transition impossible : « %s » → « %s ».', $line->status->label(), $to->label()));
        }
        $this->tx->run(function () use ($line, $to) {
            $now = new \DateTimeImmutable();
            match ($to) {
                LineStatus::PREPARING => $line->startedAt = $now,
                LineStatus::READY => $line->readyAt = $now,
                LineStatus::SERVED => $line->servedAt = $now,
                default => null,
            };
            $line->status = $to;
        });
    }

    /** Annule un plat : stock restitué s'il n'est pas lancé, sinon déclaré perdu. */
    public function cancelLine(OrderLine $line, ?User $by = null): void
    {
        $this->assertOpen($line->order);
        if (!$line->status->canTransitionTo(LineStatus::CANCELLED)) {
            throw new BusinessRuleViolation('Un plat déjà servi ou annulé ne peut plus être annulé.');
        }
        $this->tx->run(function () use ($line, $by) {
            if (LineStatus::PENDING === $line->status) {
                $this->stock->restore($line, $by);
            } else {
                $line->wasted = true;
            }
            $line->status = LineStatus::CANCELLED;
        });
    }

    /** Encaissement : tous les plats doivent être servis (ou annulés). */
    public function pay(CustomerOrder $order): void
    {
        $this->assertOpen($order);
        if ($order->hasActiveLines()) {
            throw new BusinessRuleViolation('Des plats ne sont pas encore servis : impossible d\'encaisser.');
        }
        $served = $order->lines->filter(fn (OrderLine $l) => LineStatus::SERVED === $l->status);
        if ($served->isEmpty()) {
            throw new BusinessRuleViolation('Rien à encaisser : annulez plutôt la commande.');
        }
        $this->tx->run(function () use ($order) {
            $order->status = OrderStatus::PAID;
            $order->paidAt = new \DateTimeImmutable();
        });
    }

    /** Annulation d'une commande vide ou dont tous les plats sont annulés. */
    public function cancelOrder(CustomerOrder $order): void
    {
        $this->assertOpen($order);
        foreach ($order->lines as $line) {
            if (LineStatus::CANCELLED !== $line->status) {
                throw new BusinessRuleViolation('Annulez d\'abord tous les plats de la commande.');
            }
        }
        $this->tx->run(fn () => $order->status = OrderStatus::CANCELLED);
    }

    private function buildLine(CustomerOrder $order, Dish $dish, int $quantity, string $unitPrice, ?string $note): OrderLine
    {
        $line = new OrderLine();
        $line->order = $order;
        $line->dish = $dish;
        $line->dishName = (string) $dish->name;
        $line->quantity = $quantity;
        $line->unitPrice = $unitPrice;
        $line->vatRate = $dish->vatRate;
        $line->unitCost = $this->resolver->costOfDish($dish);
        $line->note = $note;
        $order->lines->add($line);
        $this->em->persist($line);

        return $line;
    }

    private function assertOpen(CustomerOrder $order): void
    {
        if (!$order->isOpen()) {
            throw new BusinessRuleViolation('Cette commande n\'est plus ouverte.');
        }
    }

    private function assertOnCard(Dish $dish): void
    {
        if (!$dish->active) {
            throw new BusinessRuleViolation(\sprintf('Le plat « %s » n\'est plus à la carte.', $dish));
        }
    }
}
