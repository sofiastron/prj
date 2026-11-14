<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Un « temps » d'un menu (Entrée, Plat, Dessert) avec les plats au choix. */
#[ORM\Entity]
class MenuCourse
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?Menu $menu = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    public ?string $label = null;

    #[ORM\Column]
    public int $position = 1;

    /** @var Collection<int, Dish> */
    #[ORM\ManyToMany(targetEntity: Dish::class)]
    #[ORM\JoinTable(name: 'menu_course_dish')]
    public Collection $dishes;

    public function __construct()
    {
        $this->dishes = new ArrayCollection();
    }
}
