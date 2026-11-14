<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class InventoryLine
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?Inventory $inventory = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public ?Ingredient $ingredient = null;

    /** Stock théorique (en unité de base) : photographié au démarrage, recalculé à la validation. */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    public string $theoreticalQuantity = '0.000';

    /** Quantité réellement comptée (en unité de base) ; null = non compté. */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3, nullable: true)]
    public ?string $countedQuantity = null;

    /** Écart = compté − théorique (renseigné à la validation). */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3, nullable: true)]
    public ?string $variance = null;

    /** Valeur de l'écart au coût moyen (MAD). */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    public ?string $varianceValue = null;
}
