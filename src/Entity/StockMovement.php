<?php

namespace App\Entity;

use App\Enum\LossReason;
use App\Enum\MovementType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Journal (append-only) de tous les mouvements de stock : base d'audit et de statistiques. */
#[ORM\Entity]
#[ORM\Index(columns: ['created_at'], name: 'idx_movement_created')]
class StockMovement
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public ?Ingredient $ingredient = null;

    #[ORM\Column(length: 12, enumType: MovementType::class)]
    public MovementType $type = MovementType::SALE;

    /** Quantité signée en unité de base : négative = sortie de stock. */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    public string $quantity = '0.000';

    /** Coût par unité de base au moment du mouvement. */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 6)]
    public string $unitCost = '0.000000';

    #[ORM\Column(length: 15, nullable: true, enumType: LossReason::class)]
    public ?LossReason $lossReason = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $comment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?User $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?OrderLine $orderLine = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?PurchaseOrder $purchaseOrder = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?Inventory $inventory = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
