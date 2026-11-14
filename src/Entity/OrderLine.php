<?php

namespace App\Entity;

use App\Enum\LineStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Plat commandé. Prix, TVA et coût sont « figés » à la commande : modifier ensuite
 * le prix d'achat d'un ingrédient ou le prix du plat ne réécrit pas l'historique.
 */
#[ORM\Entity]
#[ORM\Index(columns: ['status'], name: 'idx_order_line_status')]
class OrderLine
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?CustomerOrder $order = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public ?Dish $dish = null;

    #[ORM\Column(length: 120)]
    public string $dishName = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?OrderMenu $orderMenu = null;

    #[ORM\Column]
    public int $quantity = 1;

    /** Prix unitaire TTC encaissé (part du prix de la formule si le plat vient d'un menu). */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $unitPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    public string $vatRate = '10.00';

    /** Coût matière unitaire au moment de la commande (CUMP). */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    public string $unitCost = '0.0000';

    #[ORM\Column(length: 12, enumType: LineStatus::class)]
    public LineStatus $status = LineStatus::PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $note = null;

    /** Plat annulé après lancement en cuisine : les ingrédients sont perdus (non restitués au stock). */
    #[ORM\Column]
    public bool $wasted = false;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $readyAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $servedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
