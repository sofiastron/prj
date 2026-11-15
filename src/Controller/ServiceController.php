<?php

namespace App\Controller;

use App\Entity\CustomerOrder;
use App\Entity\DiningTable;
use App\Entity\Dish;
use App\Entity\Menu;
use App\Entity\OrderLine;
use App\Entity\User;
use App\Enum\DishCategory;
use App\Enum\LineStatus;
use App\Enum\OrderStatus;
use App\Service\AvailabilityService;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Salle : plan des tables et prise de commande (serveurs et gérant). */
#[Route('/service')]
class ServiceController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrderService $orders,
        private readonly AvailabilityService $availability,
    ) {
    }

    #[Route('/tables', name: 'service_tables')]
    public function tables(): Response
    {
        $open = [];
        foreach ($this->em->getRepository(CustomerOrder::class)->findBy(['status' => OrderStatus::OPEN]) as $o) {
            $open[$o->table->id] = $o;
        }

        return $this->render('service/tables.html.twig', [
            'tables' => $this->em->getRepository(DiningTable::class)->findBy([], ['id' => 'ASC']),
            'open' => $open,
        ]);
    }

    #[Route('/tables/{id}/open', name: 'service_table_open', methods: ['POST'])]
    public function open(DiningTable $table, Request $request): RedirectResponse
    {
        $this->guard($request);
        /** @var User $user */
        $user = $this->getUser();
        $existing = $this->orders->openOrderFor($table);
        if (null !== $existing) {
            return $this->redirectToRoute('service_order', ['id' => $existing->id]);
        }
        $order = null;
        $this->attempt(function () use (&$order, $table, $user, $request) {
            $order = $this->orders->open($table, $user, max(1, $request->request->getInt('covers', 1)));
        });

        return null === $order ? $this->redirectToRoute('service_tables') : $this->redirectToRoute('service_order', ['id' => $order->id]);
    }

    #[Route('/orders/{id}', name: 'service_order', methods: ['GET'])]
    public function order(CustomerOrder $order): Response
    {
        $sections = [];
        foreach (DishCategory::cases() as $category) {
            $items = [];
            foreach ($this->em->getRepository(Dish::class)->findBy(['category' => $category, 'active' => true], ['name' => 'ASC']) as $dish) {
                $missing = $this->availability->missingIngredients($dish);
                $items[] = [
                    'dish' => $dish,
                    'available' => [] === $missing,
                    'max' => $this->availability->maxPortions($dish),
                    'missing' => array_map(fn ($i) => (string) $i->name, $missing),
                ];
            }
            if ($items) {
                $sections[] = ['category' => $category, 'items' => $items];
            }
        }
        $menus = [];
        foreach ($this->em->getRepository(Menu::class)->findBy(['active' => true], ['name' => 'ASC']) as $menu) {
            $courses = [];
            foreach ($menu->courses as $course) {
                $courses[] = ['course' => $course, 'dishes' => array_values(array_filter($course->dishes->toArray(), fn (Dish $d) => $this->availability->isAvailable($d)))];
            }
            $menus[] = ['menu' => $menu, 'available' => $this->availability->isMenuAvailable($menu), 'courses' => $courses];
        }

        return $this->render('service/order.html.twig', ['order' => $order, 'sections' => $sections, 'menus' => $menus]);
    }

    #[Route('/orders/{id}/dish', name: 'service_order_dish', methods: ['POST'])]
    public function addDish(CustomerOrder $order, Request $request): RedirectResponse
    {
        $this->guard($request);
        $dish = $this->em->find(Dish::class, $request->request->getInt('dish'));
        if (null === $dish) {
            throw $this->createNotFoundException();
        }
        $note = trim((string) $request->request->get('note', ''));
        $this->attempt(
            fn () => $this->orders->addDish($order, $dish, $request->request->getInt('quantity', 1), '' === $note ? null : mb_substr($note, 0, 250), $this->getUser()),
            \sprintf('« %s » envoyé en cuisine.', $dish),
        );

        return $this->redirectToRoute('service_order', ['id' => $order->id]);
    }

    #[Route('/orders/{id}/menu', name: 'service_order_menu', methods: ['POST'])]
    public function addMenu(CustomerOrder $order, Request $request): RedirectResponse
    {
        $this->guard($request);
        $menu = $this->em->find(Menu::class, $request->request->getInt('menu'));
        if (null === $menu) {
            throw $this->createNotFoundException();
        }
        $choices = array_map('intval', (array) $request->request->all('choice'));
        $this->attempt(
            fn () => $this->orders->addMenu($order, $menu, $choices, $this->getUser()),
            \sprintf('Menu « %s » envoyé en cuisine.', $menu),
        );

        return $this->redirectToRoute('service_order', ['id' => $order->id]);
    }

    #[Route('/lines/{id}/{action}', name: 'service_line', requirements: ['action' => 'serve|cancel'], methods: ['POST'])]
    public function line(OrderLine $line, string $action, Request $request): RedirectResponse
    {
        $this->guard($request);
        $orderId = $line->order->id;
        'serve' === $action
            ? $this->attempt(fn () => $this->orders->advance($line, LineStatus::SERVED), 'Plat servi.')
            : $this->attempt(fn () => $this->orders->cancelLine($line, $this->getUser()), 'Plat annulé.');

        return $this->redirectToRoute('service_order', ['id' => $orderId]);
    }

    #[Route('/orders/{id}/pay', name: 'service_order_pay', methods: ['POST'])]
    public function pay(CustomerOrder $order, Request $request): RedirectResponse
    {
        $this->guard($request);
        $ok = $this->attempt(fn () => $this->orders->pay($order), 'Commande encaissée.');

        return $ok ? $this->redirectToRoute('service_tables') : $this->redirectToRoute('service_order', ['id' => $order->id]);
    }

    #[Route('/orders/{id}/cancel', name: 'service_order_cancel', methods: ['POST'])]
    public function cancel(CustomerOrder $order, Request $request): RedirectResponse
    {
        $this->guard($request);
        $ok = $this->attempt(fn () => $this->orders->cancelOrder($order), 'Commande annulée.');

        return $ok ? $this->redirectToRoute('service_tables') : $this->redirectToRoute('service_order', ['id' => $order->id]);
    }
}
