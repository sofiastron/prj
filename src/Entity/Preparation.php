<?php

namespace App\Entity;

use App\Enum\Unit;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Préparation de base réutilisable (sauce, pâte, fond…). Elle produit `yieldQuantity`
 * `yieldUnit` à partir de ses lignes de recette (ingrédients ou autres préparations).
 */
#[ORM\Entity]
#[UniqueEntity('name', message: 'Une préparation porte déjà ce nom.')]
class Preparation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    #[Assert\NotBlank]
    public ?string $name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    #[Assert\Positive]
    public string $yieldQuantity = '1.000';

    #[ORM\Column(length: 5, enumType: Unit::class)]
    public Unit $yieldUnit = Unit::KG;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $notes = null;

    /** @var Collection<int, PreparationLine> */
    #[ORM\OneToMany(mappedBy: 'preparation', targetEntity: PreparationLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
