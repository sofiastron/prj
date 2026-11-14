<?php

namespace App\Entity;

use App\Enum\Unit;
use App\Service\Decimal;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PurchaseLine
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?PurchaseOrder $purchaseOrder = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public ?Ingredient $ingredient = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    public string $quantity = '0.000';

    #[ORM\Column(length: 5, enumType: Unit::class)]
    public Unit $unit = Unit::KG;

    /** Prix d'achat HT par unité d'achat (ex. MAD/kg). */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4)]
    public string $unitPrice = '0.0000';

    public function total(): string
    {
        return Decimal::round(Decimal::mul($this->quantity, $this->unitPrice), 2);
    }
}
