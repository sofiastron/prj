<?php

namespace App\Tests\Functional;

use App\Entity\User;

/** Niveau 3 : chaque rôle voit ses écrans, et uniquement ses écrans. */
class AccessAndPagesTest extends FunctionalTestCase
{
    public function testAnonymousIsRedirectedToLoginExceptPublicCard(): void
    {
        foreach (['/', '/service/tables', '/kitchen', '/admin/dishes', '/dashboard', '/kitchen/losses'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseRedirects('http://localhost/login', null, $url);
        }
        $this->client->request('GET', '/carte');
        $this->assertResponseIsSuccessful();
        $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
    }

    public function testServerCanOnlyUseTheDiningRoom(): void
    {
        $this->loginAs(User::ROLE_SERVER);
        $this->page('/service/tables');
        $this->assertResponseIsSuccessful();
        foreach (['/kitchen', '/admin/dishes', '/admin/ingredients', '/dashboard', '/admin/users', '/kitchen/losses'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseStatusCodeSame(403, $url);
        }
    }

    public function testKitchenCanOnlyUseTheKitchenScreens(): void
    {
        $this->loginAs(User::ROLE_KITCHEN);
        foreach (['/kitchen', '/kitchen/losses'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful($url);
        }
        foreach (['/service/tables', '/admin/dishes', '/dashboard'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseStatusCodeSame(403, $url);
        }
    }

    public function testManagerSeesEveryPage(): void
    {
        $this->loginAs(User::ROLE_MANAGER);
        $urls = [
            '/dashboard', '/dashboard?days=7', '/service/tables', '/kitchen', '/kitchen/losses', '/carte',
            '/admin/ingredients', '/admin/ingredients/new', '/admin/ingredients/'.$this->rice->id.'/edit', '/admin/ingredients/'.$this->rice->id.'/allergens',
            '/admin/suppliers', '/admin/suppliers/new', '/admin/tables', '/admin/tables/new', '/admin/users', '/admin/users/new',
            '/admin/dishes', '/admin/dishes/new', '/admin/dishes/'.$this->dish->id.'/edit',
            '/admin/preparations', '/admin/preparations/new',
            '/admin/menus', '/admin/menus/new', '/admin/menus/'.$this->menu->id.'/edit',
            '/admin/purchases', '/admin/inventories', '/admin/movements', '/admin/movements?type=sale',
        ];
        foreach ($urls as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful($url);
        }
    }

    public function testHomeRedirectsEachRoleToItsScreen(): void
    {
        foreach ([User::ROLE_MANAGER => '/dashboard', User::ROLE_KITCHEN => '/kitchen', User::ROLE_SERVER => '/service/tables'] as $role => $target) {
            $this->loginAs($role);
            $this->client->request('GET', '/');
            $this->assertResponseRedirects($target, null, $role);
        }
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $this->loginAs(User::ROLE_SERVER);
        $table = $this->em()->getRepository(\App\Entity\DiningTable::class)->findOneBy([]);
        $this->client->request('POST', '/service/tables/'.$table->id.'/open', ['covers' => 2]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testPublicCardShowsAllergenWarningForUnreliableIngredient(): void
    {
        $this->page('/carte');
        $this->assertResponseIsSuccessful();
        // Riz créé par la fabrique de test : fiche allergène inconnue => avertissement affiché.
        $this->assertStringContainsString('Information allergènes incomplète', $this->client->getResponse()->getContent());
        $this->assertStringContainsString('Riz cantonais', $this->client->getResponse()->getContent());
    }
}
