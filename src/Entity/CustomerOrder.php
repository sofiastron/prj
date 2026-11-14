<?php

namespace App\Entity;

use App\Enum\LineStatus;
use App\Enum\OrderStatus;
use App\Service\Decimal;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customer_order')]
class CustomerOrder
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public ?DiningTable $table = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public ?User $server = null;

    #[ORM\Column(length: 10, enumType: OrderStatus::class)]
    public OrderStatus $status = OrderStatus::OPEN;

    #[ORM\Column]
    public int $covers = 1;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $paidAt = null;

    /** @var Collection<int, OrderLine> */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderLine::class, cascade: ['persist'])]
    #[ORM\OrderBy(['id' => 'ASC'])]
    public Collection $lines;

    /** @var Collection<int, OrderMenu> */
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderMenu::class, cascade: ['persist'])]
    public Collection $menus;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
        $this->menus = new ArrayCollection();
    }

    /** Montant TTC à payer (plats annulés exclus). */
    public function total(): string
    {
        $total = '0';
        foreach ($this->lines as $line) {
            if (LineStatus::CANCELLED !== $line->status) {
                $total = Decimal::add($total, Decimal::mul($line->unitPrice, (string) $line->quantity, 2), 2);
            }
        }

        return $total;
    }

    public function isOpen(): bool
    {
        return OrderStatus::OPEN === $this->status;
    }

    public function hasActiveLines(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->status->isActive()) {
                return true;
            }
        }

        return false;
    }
}
