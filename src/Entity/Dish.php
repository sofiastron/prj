<?php

namespace App\Entity;

use App\Enum\DishCategory;
use App\Service\Decimal;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[UniqueEntity('name', message: 'Un plat porte déjà ce nom.')]
class Dish
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    #[Assert\NotBlank]
    public ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null;

    #[ORM\Column(length: 10, enumType: DishCategory::class)]
    public DishCategory $category = DishCategory::MAIN;

    /** Prix de vente TTC (MAD). */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    public string $price = '0.00';

    /** Taux de TVA en % (10 % pour la restauration au Maroc). */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\Range(min: 0, max: 100)]
    public string $vatRate = '10.00';

    /** Plat présent sur la carte (indépendant de la disponibilité des stocks). */
    #[ORM\Column]
    public bool $active = true;

    /** @var Collection<int, DishLine> */
    #[ORM\OneToMany(mappedBy: 'dish', targetEntity: DishLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    /** Prix hors taxe. */
    public function priceExclVat(): string
    {
        return Decimal::div($this->price, Decimal::add('1', Decimal::div($this->vatRate, '100')), 4);
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
