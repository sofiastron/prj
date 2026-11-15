<?php

namespace App\Controller;

use App\Entity\OrderLine;
use App\Enum\LineStatus;
use App\Enum\OrderStatus;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Écran cuisine : file des plats avec leur statut (cuisine et gérant). */
#[Route('/kitchen')]
class KitchenController extends BaseController
{
    #[Route('', name: 'kitchen_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $lines = $em->createQueryBuilder()
            ->select('l', 'o', 't')->from(OrderLine::class, 'l')
            ->join('l.order', 'o')->join('o.table', 't')
            ->where('o.status = :open')->andWhere('l.status IN (:st)')
            ->setParameter('open', OrderStatus::OPEN)
            ->setParameter('st', [LineStatus::PENDING, LineStatus::PREPARING, LineStatus::READY])
            ->orderBy('l.createdAt', 'ASC')->getQuery()->getResult();
        $columns = [LineStatus::PENDING->value => [], LineStatus::PREPARING->value => [], LineStatus::READY->value => []];
        foreach ($lines as $l) {
            $columns[$l->status->value][] = $l;
        }

        return $this->render('kitchen/index.html.twig', ['columns' => $columns, 'now' => new \DateTimeImmutable()]);
    }

    /** La cuisine lance un plat (PENDING → PREPARING) ou le déclare prêt (PREPARING → READY). */
    #[Route('/lines/{id}/{to}', name: 'kitchen_line', requirements: ['to' => 'preparing|ready'], methods: ['POST'])]
    public function advance(OrderLine $line, string $to, Request $request, OrderService $orders): RedirectResponse
    {
        $this->guard($request);
        $this->attempt(fn () => $orders->advance($line, LineStatus::from($to)));

        return $this->redirectToRoute('kitchen_index');
    }
}
