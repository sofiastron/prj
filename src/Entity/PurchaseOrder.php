<?php

namespace App\Entity;

use App\Enum\PurchaseStatus;
use App\Service\Decimal;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PurchaseOrder
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public ?Supplier $supplier = null;

    #[ORM\Column(length: 10, enumType: PurchaseStatus::class)]
    public PurchaseStatus $status = PurchaseStatus::DRAFT;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $orderedAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $receivedAt = null;

    /** @var Collection<int, PurchaseLine> */
    #[ORM\OneToMany(mappedBy: 'purchaseOrder', targetEntity: PurchaseLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $lines;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
    }

    public function total(): string
    {
        $t = '0.00';
        foreach ($this->lines as $l) {
            $t = Decimal::add($t, $l->total(), 2);
        }

        return $t;
    }
}
