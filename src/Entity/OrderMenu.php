<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Menu commandé : regroupe les plats choisis et fige le prix de la formule. */
#[ORM\Entity]
class OrderMenu
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'menus')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ?CustomerOrder $order = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    public ?Menu $menu = null;

    #[ORM\Column(length: 120)]
    public string $menuName = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    public string $price = '0.00';
}
