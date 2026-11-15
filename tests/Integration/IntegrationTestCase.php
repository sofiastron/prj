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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Chaque test s'exécute dans une transaction annulée à la fin : la base de test reste vierge.
 * Prérequis : base « resto_test » migrée (voir INSTALL.md, section Tests).
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    use TestFactories;

    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $conn = $this->em->getConnection();
        while ($conn->isTransactionActive()) {
            $conn->rollBack();
        }
        $this->em->clear();
        parent::tearDown();
    }
}
