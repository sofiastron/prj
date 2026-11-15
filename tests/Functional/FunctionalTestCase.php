<?php

namespace App\Tests\Functional;

use App\Entity\Dish;
use App\Entity\Ingredient;
use App\Entity\Menu;
use App\Entity\MenuCourse;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\Dimension;
use App\Enum\Unit;
use App\Tests\Integration\TestFactories;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

/** Test HTTP de bout en bout : vrai noyau Symfony, vraie base MySQL, transaction annulée à la fin. */
abstract class FunctionalTestCase extends WebTestCase
{
    use TestFactories;

    protected EntityManagerInterface $em;
    protected KernelBrowser $client;
    protected Ingredient $rice;
    protected Dish $dish;
    protected Menu $menu;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        // Pas de super::setUp() : le navigateur démarre le noyau lui-même.
        $this->client = static::createClient();
        $this->client->disableReboot();   // même connexion MySQL pour le test et les requêtes
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->em->getConnection()->beginTransaction();

        $this->rice = $this->ingredient('Riz', Dimension::MASS, '5000', '0.014');
        $this->dish = $this->dish('Riz cantonais', '40.00', [[$this->rice, '200', Unit::G]]);
        $starter = $this->dish('Soupe', '20.00', [[$this->ingredient('Eau', Dimension::VOLUME, '9000'), '30', Unit::CL]]);
        $this->menu = new Menu();
        $this->menu->name = 'Formule';
        $this->menu->price = '50.00';
        foreach ([['Entrée', 1, $starter], ['Plat', 2, $this->dish]] as [$label, $pos, $d]) {
            $c = new MenuCourse();
            $c->menu = $this->menu;
            $c->label = $label;
            $c->position = $pos;
            $c->dishes->add($d);
            $this->menu->courses->add($c);
        }
        $this->em->persist($this->menu);
        $this->supplier = new Supplier();
        $this->supplier->name = 'Fournisseur test';
        $this->em->persist($this->supplier);
        $this->table();
        $this->em->flush();
    }

    /** Le conteneur réinitialise l'EntityManager après chaque requête : on relit toujours l'instance courante. */
    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function refresh(object $entity): object
    {
        $em = $this->em();
        $em->clear();

        return $em->find($entity::class, $entity->id);
    }

    protected function loginAs(string $role): User
    {
        $user = $this->user($role, strtolower($role).'@test.local');
        $this->client->loginUser($user, 'main');

        return $user;
    }

    /** Jeton CSRF « app » lu dans la page courante (comme le ferait un navigateur). */
    protected function csrf(string $url): string
    {
        $crawler = $this->client->request('GET', $url);
        $token = $crawler->filter('input[name=_token]')->first()->attr('value');
        $this->assertNotEmpty($token, 'aucun jeton CSRF trouvé sur '.$url);

        return $token;
    }

    protected function page(string $url): Crawler
    {
        return $this->client->request('GET', $url);
    }

    protected function tearDown(): void
    {
        $conn = $this->em->getConnection();
        while ($conn->isTransactionActive()) {
            $conn->rollBack();
        }
        parent::tearDown();
    }
}
