<?php

namespace App\Tests\Functional;

use App\Entity\CustomerOrder;
use App\Entity\Dish;
use App\Entity\Ingredient;
use App\Entity\Preparation;
use App\Entity\User;
use App\Enum\OrderStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parcourt toutes les pages avec le jeu de démonstration (fixtures) chargé dans la base de test.
 * Ignoré si les fixtures ne sont pas chargées :
 *   php bin/console doctrine:fixtures:load --env=test -n && phpunit --filter DemoDataSmokeTest
 * (puis repurger la base de test avant de relancer la suite complète).
 */
class DemoDataSmokeTest extends WebTestCase
{
    public function testEveryPageRendersWithDemoData(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $manager = $em->getRepository(User::class)->findOneBy(['email' => 'gerant@resto.test']);
        if (null === $manager) {
            $this->markTestSkipped('Fixtures non chargées dans la base de test.');
        }
        $client->loginUser($manager, 'main');

        $urls = ['/dashboard', '/carte', '/kitchen', '/kitchen/losses', '/service/tables', '/admin/ingredients', '/admin/dishes', '/admin/preparations', '/admin/menus', '/admin/purchases', '/admin/inventories', '/admin/movements', '/admin/suppliers', '/admin/tables', '/admin/users'];
        foreach ($em->getRepository(Dish::class)->findAll() as $d) {
            $urls[] = '/admin/dishes/'.$d->id.'/edit';
        }
        foreach ($em->getRepository(Preparation::class)->findAll() as $p) {
            $urls[] = '/admin/preparations/'.$p->id.'/edit';
        }
        foreach ($em->getRepository(Ingredient::class)->findAll() as $i) {
            $urls[] = '/admin/ingredients/'.$i->id.'/edit';
        }
        foreach ($em->getRepository(CustomerOrder::class)->findBy(['status' => OrderStatus::OPEN]) as $o) {
            $urls[] = '/service/orders/'.$o->id;
        }
        foreach ($urls as $url) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful($url);
        }
        $this->assertGreaterThan(100, \count($urls));
    }
}
