<?php

namespace App\Entity;

use App\Enum\AllergenStatus;
use App\Enum\Dimension;
use App\Service\Decimal;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Produit brut acheté et stocké. Le stock est exprimé en unité de base de la dimension
 * (g, ml ou pièce) ; le coût moyen pondéré (CUMP) est exprimé par unité de base.
 */
#[ORM\Entity]
#[UniqueEntity('name', message: 'Un ingrédient porte déjà ce nom.')]
class Ingredient
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    #[Assert\NotBlank]
    public ?string $name = null;

    #[ORM\Column(length: 10, enumType: Dimension::class)]
    public Dimension $dimension = Dimension::MASS;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    public string $stockQuantity = '0.000';

    /** Coût moyen pondéré en MAD par unité de base (ex. MAD/g). */
    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 6)]
    public string $averageCost = '0.000000';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    #[Assert\PositiveOrZero]
    public string $minStock = '0.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    #[Assert\PositiveOrZero]
    public string $targetStock = '0.000';

    #[ORM\Column(length: 32, nullable: true)]
    #[Assert\Regex('/^\d{8,14}$/', message: 'Un code-barres contient 8 à 14 chiffres.')]
    public ?string $barcode = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?Supplier $supplier = null;

    /** @var list<string> valeurs de App\Enum\Allergen */
    #[ORM\Column(type: Types::JSON)]
    public array $allergens = [];

    /** @var list<string> traces possibles (« peut contenir ») */
    #[ORM\Column(type: Types::JSON)]
    public array $traces = [];

    #[ORM\Column(length: 12, enumType: AllergenStatus::class)]
    public AllergenStatus $allergenStatus = AllergenStatus::UNKNOWN;

    /** « off » (Open Food Facts) ou « manual » (déclaré par le gérant). */
    #[ORM\Column(length: 10, nullable: true)]
    public ?string $allergenSource = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $offProductName = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $offFetchedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $offError = null;

    public function isLow(): bool
    {
        return Decimal::cmp($this->stockQuantity, $this->minStock) <= 0 && !Decimal::isZero($this->minStock);
    }

    public function isOut(): bool
    {
        return Decimal::cmp($this->stockQuantity, '0') <= 0;
    }

    public function stockValue(): string
    {
        return Decimal::round(Decimal::mul($this->stockQuantity, $this->averageCost), 2);
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
