<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formule composée (ex. entrée + plat + dessert au choix) vendue à prix fixe.
 * Ce n'est PAS un plat : elle ne consomme aucun stock par elle-même, seuls les plats choisis le font.
 */
#[ORM\Entity]
#[UniqueEntity('name', message: 'Un menu porte déjà ce nom.')]
class Menu
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    #[Assert\NotBlank]
    public ?string $name = null;

    /** Prix TTC de la formule. */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    public string $price = '0.00';

    #[ORM\Column]
    public bool $active = true;

    /** @var Collection<int, MenuCourse> */
    #[ORM\OneToMany(mappedBy: 'menu', targetEntity: MenuCourse::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public Collection $courses;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
