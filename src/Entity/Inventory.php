<?php

namespace App\Entity;

use App\Enum\InventoryStatus;
use App\Service\Decimal;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Inventory
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 10, enumType: InventoryStatus::class)]
    public InventoryStatus $status = InventoryStatus::DRAFT;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?User $createdBy = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $validatedAt = null;

    /** @var Collection<int, InventoryLine> */
    #[ORM\OneToMany(mappedBy: 'inventory', targetEntity: InventoryLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $lines;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
    }

    /** Valeur totale des écarts (MAD) : négatif = manque. */
    public function totalVarianceValue(): string
    {
        $t = '0.00';
        foreach ($this->lines as $l) {
            if (null !== $l->varianceValue) {
                $t = Decimal::add($t, $l->varianceValue, 2);
            }
        }

        return $t;
    }
}
