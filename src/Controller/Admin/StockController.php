<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Ingredient;
use App\Entity\Inventory;
use App\Entity\InventoryLine;
use App\Entity\PurchaseLine;
use App\Entity\PurchaseOrder;
use App\Entity\StockMovement;
use App\Entity\Supplier;
use App\Enum\MovementType;
use App\Enum\Unit;
use App\Exception\BusinessRuleViolation;
use App\Service\DashboardService;
use App\Service\Decimal;
use App\Service\InventoryService;
use App\Service\PurchaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Back-office : achats, inventaires et journal des mouvements de stock. */
#[Route('/admin')]
class StockController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PurchaseService $purchases,
        private readonly InventoryService $inventories,
    ) {
    }

    // ---- Achats ------------------------------------------------------------

    #[Route('/purchases', name: 'admin_purchases')]
    public function purchases(): Response
    {
        return $this->render('admin/purchases.html.twig', [
            'purchases' => $this->em->getRepository(PurchaseOrder::class)->findBy([], ['id' => 'DESC']),
            'suppliers' => $this->em->getRepository(Supplier::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/purchases/new', name: 'admin_purchase_new', methods: ['POST'])]
    public function newPurchase(Request $request): RedirectResponse
    {
        $this->guard($request);
        $supplier = $this->em->find(Supplier::class, $request->request->getInt('supplier'));
        if (null === $supplier) {
            $this->addFlash('error', 'Choisissez un fournisseur.');

            return $this->redirectToRoute('admin_purchases');
        }

        return $this->redirectToRoute('admin_purchase', ['id' => $this->purchases->create($supplier)->id]);
    }

    /** Crée un brouillon de bon de commande à partir des suggestions de réapprovisionnement d'un fournisseur. */
    #[Route('/purchases/from-suggestions/{id}', name: 'admin_purchase_from_suggestions', methods: ['POST'])]
    public function fromSuggestions(Supplier $supplier, Request $request, DashboardService $dashboard): RedirectResponse
    {
        $this->guard($request);
        $po = $this->purchases->create($supplier);
        $count = 0;
        foreach ($dashboard->restockSuggestions() as $s) {
            $ingredient = $s['ingredient'];
            if ($ingredient->supplier?->id !== $supplier->id) {
                continue;
            }
            // Prix proposé : dernier coût moyen exprimé dans l'unité de commande la plus naturelle.
            $unit = match ($ingredient->dimension->value) {
                'mass' => Unit::KG, 'volume' => Unit::L, default => Unit::PIECE,
            };
            $this->purchases->addLine(
                $po, $ingredient,
                Decimal::round($unit->fromBase($s['suggested']), 3),
                $unit,
                Decimal::round(Decimal::mul($ingredient->averageCost, $unit->factor()), 4),
            );
            ++$count;
        }
        $this->addFlash('success', $count." ligne(s) pré-remplie(s) d'après les suggestions : vérifiez quantités et prix avant de commander.");

        return $this->redirectToRoute('admin_purchase', ['id' => $po->id]);
    }

    #[Route('/purchases/{id}', name: 'admin_purchase', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function purchase(PurchaseOrder $po): Response
    {
        return $this->render('admin/purchase.html.twig', [
            'po' => $po,
            'ingredients' => $this->em->getRepository(Ingredient::class)->findBy([], ['name' => 'ASC']),
            'units' => Unit::cases(),
        ]);
    }

    #[Route('/purchases/{id}/lines', name: 'admin_purchase_line_add', methods: ['POST'])]
    public function addLine(PurchaseOrder $po, Request $request): RedirectResponse
    {
        $this->guard($request);
        $this->attempt(function () use ($po, $request) {
            $ingredient = $this->em->find(Ingredient::class, $request->request->getInt('ingredient')) ?? throw new BusinessRuleViolation('Choisissez un ingrédient.');
            $unit = Unit::tryFrom((string) $request->request->get('unit')) ?? throw new BusinessRuleViolation('Unité invalide.');
            $this->purchases->addLine($po, $ingredient, $this->decimal($request, 'quantity'), $unit, $this->decimal($request, 'price'));
        }, 'Ligne ajoutée.');

        return $this->redirectToRoute('admin_purchase', ['id' => $po->id]);
    }

    #[Route('/purchase-lines/{id}/delete', name: 'admin_purchase_line_delete', methods: ['POST'])]
    public function deleteLine(PurchaseLine $line, Request $request): RedirectResponse
    {
        $this->guard($request);
        $po = $line->purchaseOrder;
        $this->attempt(fn () => $this->purchases->removeLine($po, $line), 'Ligne supprimée.');

        return $this->redirectToRoute('admin_purchase', ['id' => $po->id]);
    }

    #[Route('/purchases/{id}/{action}', name: 'admin_purchase_action', requirements: ['action' => 'order|receive|cancel'], methods: ['POST'])]
    public function action(PurchaseOrder $po, string $action, Request $request): RedirectResponse
    {
        $this->guard($request);
        match ($action) {
            'order' => $this->attempt(fn () => $this->purchases->markOrdered($po), 'Bon de commande marqué comme envoyé.'),
            'receive' => $this->attempt(fn () => $this->purchases->receive($po, $this->getUser()), 'Marchandise réceptionnée : stocks et coûts moyens mis à jour.'),
            'cancel' => $this->attempt(fn () => $this->purchases->cancel($po), 'Bon de commande annulé.'),
        };

        return $this->redirectToRoute('admin_purchase', ['id' => $po->id]);
    }

    // ---- Inventaires -------------------------------------------------------

    #[Route('/inventories', name: 'admin_inventories')]
    public function inventories(): Response
    {
        return $this->render('admin/inventories.html.twig', [
            'inventories' => $this->em->getRepository(Inventory::class)->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/inventories/start', name: 'admin_inventory_start', methods: ['POST'])]
    public function start(Request $request): RedirectResponse
    {
        $this->guard($request);
        $inventory = null;
        $this->attempt(function () use (&$inventory) {
            $inventory = $this->inventories->start($this->getUser());
        });

        if (null === $inventory) {
            $open = $this->em->getRepository(Inventory::class)->findOneBy(['status' => \App\Enum\InventoryStatus::DRAFT]);

            return null !== $open ? $this->redirectToRoute('admin_inventory', ['id' => $open->id]) : $this->redirectToRoute('admin_inventories');
        }

        return $this->redirectToRoute('admin_inventory', ['id' => $inventory->id]);
    }

    #[Route('/inventories/{id}', name: 'admin_inventory', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function inventory(Inventory $inventory): Response
    {
        $lines = $inventory->lines->toArray();
        usort($lines, fn (InventoryLine $a, InventoryLine $b) => strcmp((string) $a->ingredient->name, (string) $b->ingredient->name));

        return $this->render('admin/inventory.html.twig', ['inventory' => $inventory, 'lines' => $lines]);
    }

    /** Enregistre les quantités comptées ; avec « validate », valide ensuite l'inventaire. */
    #[Route('/inventories/{id}/count', name: 'admin_inventory_count', methods: ['POST'])]
    public function count(Inventory $inventory, Request $request): RedirectResponse
    {
        $this->guard($request);
        $quantities = (array) $request->request->all('qty');
        $units = (array) $request->request->all('unit');
        $ok = $this->attempt(function () use ($inventory, $quantities, $units) {
            foreach ($inventory->lines as $line) {
                $unit = Unit::tryFrom((string) ($units[$line->id] ?? '')) ?? $line->ingredient->dimension->baseUnit();
                $this->inventories->count($line, isset($quantities[$line->id]) ? (string) $quantities[$line->id] : null, $unit);
            }
        });
        if ($ok && $request->request->has('validate')) {
            $this->attempt(fn () => $this->inventories->validate($inventory, $this->getUser()), 'Inventaire validé : stocks recalés, écarts enregistrés.');
        } elseif ($ok) {
            $this->addFlash('success', 'Comptages enregistrés.');
        }

        return $this->redirectToRoute('admin_inventory', ['id' => $inventory->id]);
    }

    #[Route('/inventories/{id}/discard', name: 'admin_inventory_discard', methods: ['POST'])]
    public function discard(Inventory $inventory, Request $request): RedirectResponse
    {
        $this->guard($request);
        $ok = $this->attempt(fn () => $this->inventories->discard($inventory), 'Inventaire supprimé.');

        return $ok ? $this->redirectToRoute('admin_inventories') : $this->redirectToRoute('admin_inventory', ['id' => $inventory->id]);
    }

    // ---- Journal des mouvements ---------------------------------------------

    #[Route('/movements', name: 'admin_movements')]
    public function movements(Request $request): Response
    {
        $type = MovementType::tryFrom((string) $request->query->get('type'));
        $criteria = null === $type ? [] : ['type' => $type];

        return $this->render('admin/movements.html.twig', [
            'movements' => $this->em->getRepository(StockMovement::class)->findBy($criteria, ['id' => 'DESC'], 200),
            'types' => MovementType::cases(), 'current' => $type,
        ]);
    }
}
