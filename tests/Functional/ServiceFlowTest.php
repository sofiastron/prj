<?php

namespace App\Tests\Functional;

use App\Entity\CustomerOrder;
use App\Entity\DiningTable;
use App\Entity\OrderLine;
use App\Entity\User;
use App\Enum\LineStatus;
use App\Enum\OrderStatus;

/** Parcours complet : ouverture de table → commande → cuisine → service → encaissement. */
class ServiceFlowTest extends FunctionalTestCase
{
    public function testFullServiceFlowThroughTheUi(): void
    {
        $this->loginAs(User::ROLE_MANAGER);   // le gérant hérite des droits serveur ET cuisine
        $table = $this->em()->getRepository(DiningTable::class)->findOneBy([]);

        // 1. ouverture de table
        $this->client->request('POST', '/service/tables/'.$table->id.'/open', ['_token' => $this->csrf('/service/tables'), 'covers' => 2]);
        $order = $this->em()->getRepository(CustomerOrder::class)->findOneBy(['table' => $table]);
        $this->assertNotNull($order);
        $this->assertResponseRedirects('/service/orders/'.$order->id);

        // 2. commande de 2 plats : stock décrémenté, plat visible en cuisine
        $url = '/service/orders/'.$order->id;
        $this->client->request('POST', $url.'/dish', ['_token' => $this->csrf($url), 'dish' => $this->dish->id, 'quantity' => 2, 'note' => 'sans oignon']);
        $this->assertSame('4600.000', $this->refresh($this->rice)->stockQuantity);
        $this->page('/kitchen');
        $this->assertStringContainsString('sans oignon', $this->client->getResponse()->getContent());

        // 3. la cuisine lance puis termine le plat
        $line = $this->em()->getRepository(OrderLine::class)->findOneBy(['order' => $order]);
        foreach (['preparing', 'ready'] as $to) {
            $this->client->request('POST', '/kitchen/lines/'.$line->id.'/'.$to, ['_token' => $this->csrf('/kitchen')]);
        }
        $this->assertSame(LineStatus::READY, $this->refresh($line)->status);

        // 4. encaisser trop tôt est refusé avec un message clair
        $this->client->request('POST', $url.'/pay', ['_token' => $this->csrf($url)]);
        $this->client->followRedirect();
        $this->assertStringContainsString('pas encore servis', $this->client->getResponse()->getContent());
        $this->assertSame(OrderStatus::OPEN, $this->refresh($order)->status);
        $order = $this->refresh($order);
        $line = $this->refresh($line);

        // 5. service puis encaissement
        $this->client->request('POST', '/service/lines/'.$line->id.'/serve', ['_token' => $this->csrf($url)]);
        $this->client->request('POST', $url.'/pay', ['_token' => $this->csrf($url)]);
        $this->assertResponseRedirects('/service/tables');
        $paid = $this->refresh($order);
        $this->assertSame(OrderStatus::PAID, $paid->status);
        $this->assertSame('80.00', $paid->total());
    }

    public function testUnavailableDishIsRefusedWithAMessageNotAnError(): void
    {
        $this->loginAs(User::ROLE_MANAGER);
        $table = $this->em()->getRepository(DiningTable::class)->findOneBy([]);
        $this->client->request('POST', '/service/tables/'.$table->id.'/open', ['_token' => $this->csrf('/service/tables'), 'covers' => 1]);
        $order = $this->em()->getRepository(CustomerOrder::class)->findOneBy(['table' => $table]);
        $url = '/service/orders/'.$order->id;

        $this->client->request('POST', $url.'/dish', ['_token' => $this->csrf($url), 'dish' => $this->dish->id, 'quantity' => 30]);   // 30 × 200 g > 5 kg
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Stock insuffisant', $this->client->getResponse()->getContent());
        $this->assertSame('5000.000', $this->em()->find(\App\Entity\Ingredient::class, $this->rice->id)->stockQuantity);
    }

    public function testMenuCanBeOrderedThroughTheUi(): void
    {
        $this->loginAs(User::ROLE_MANAGER);
        $table = $this->em()->getRepository(DiningTable::class)->findOneBy([]);
        $this->client->request('POST', '/service/tables/'.$table->id.'/open', ['_token' => $this->csrf('/service/tables'), 'covers' => 1]);
        $order = $this->em()->getRepository(CustomerOrder::class)->findOneBy(['table' => $table]);
        $url = '/service/orders/'.$order->id;
        $choices = [];
        foreach ($this->menu->courses as $c) {
            $choices[$c->id] = $c->dishes->first()->id;
        }

        $this->client->request('POST', $url.'/menu', ['_token' => $this->csrf($url), 'menu' => $this->menu->id, 'choice' => $choices]);

        $fresh = $this->refresh($order);
        $this->assertCount(2, $fresh->lines);
        $this->assertSame('50.00', $fresh->total());
    }
}
