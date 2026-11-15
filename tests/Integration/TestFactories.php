<?php

namespace App\Tests\Integration;

use App\Entity\DiningTable;
use App\Entity\Dish;
use App\Entity\DishLine;
use App\Entity\Ingredient;
use App\Entity\Preparation;
use App\Entity\PreparationLine;
use App\Entity\User;
use App\Enum\Dimension;
use App\Enum\Unit;

/** Fabriques de données de test (nécessite une propriété $em). */
trait TestFactories
{
    /** @template T of object @param class-string<T> $class @return T */
    protected function service(string $class): object
    {
        return self::getContainer()->get($class);
    }

    /** Ingrédient avec un stock exprimé en unité de base et un coût par unité de base. */
    protected function ingredient(string $name, Dimension $dimension, string $stockBase, string $costPerBase = '0'): Ingredient
    {
        $i = new Ingredient();
        $i->name = $name;
        $i->dimension = $dimension;
        $i->stockQuantity = $stockBase;
        $i->averageCost = $costPerBase;
        $this->em->persist($i);
        $this->em->flush();

        return $i;
    }

    /** @param list<array{0: Ingredient|Preparation, 1: string, 2: Unit}> $lines */
    protected function dish(string $name, string $price, array $lines, bool $active = true): Dish
    {
        $d = new Dish();
        $d->name = $name;
        $d->price = $price;
        $d->active = $active;
        foreach ($lines as [$component, $qty, $unit]) {
            $l = new DishLine();
            $l->dish = $d;
            $l->quantity = $qty;
            $l->unit = $unit;
            $component instanceof Ingredient ? $l->ingredient = $component : $l->preparation = $component;
            $d->lines->add($l);
        }
        $this->em->persist($d);
        $this->em->flush();

        return $d;
    }

    /** @param list<array{0: Ingredient|Preparation, 1: string, 2: Unit}> $lines */
    protected function preparation(string $name, string $yield, Unit $yieldUnit, array $lines): Preparation
    {
        $p = new Preparation();
        $p->name = $name;
        $p->yieldQuantity = $yield;
        $p->yieldUnit = $yieldUnit;
        foreach ($lines as [$component, $qty, $unit]) {
            $l = new PreparationLine();
            $l->preparation = $p;
            $l->quantity = $qty;
            $l->unit = $unit;
            $component instanceof Ingredient ? $l->ingredient = $component : $l->subPreparation = $component;
            $p->lines->add($l);
        }
        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    protected function user(string $role = User::ROLE_SERVER, string $email = 'serveur@test.local'): User
    {
        $u = new User();
        $u->email = $email;
        $u->displayName = 'Test';
        $u->roles = [$role];
        $u->password = 'x';
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    protected function table(string $label = 'T1'): DiningTable
    {
        $t = new DiningTable();
        $t->label = $label;
        $t->seats = 4;
        $this->em->persist($t);
        $this->em->flush();

        return $t;
    }

    protected function refresh(object $entity): object
    {
        $this->em->refresh($entity);

        return $entity;
    }
}
