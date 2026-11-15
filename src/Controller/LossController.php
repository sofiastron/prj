<?php

namespace App\Controller;

use App\Entity\Ingredient;
use App\Entity\StockMovement;
use App\Enum\LossReason;
use App\Enum\MovementType;
use App\Enum\Unit;
use App\Exception\BusinessRuleViolation;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Déclaration des pertes (casse, péremption, erreur en cuisine) : cuisine et gérant. */
#[Route('/kitchen/losses')]
class LossController extends BaseController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly StockService $stock)
    {
    }

    #[Route('', name: 'losses', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/losses.html.twig', [
            'losses' => $this->em->getRepository(StockMovement::class)->findBy(['type' => MovementType::LOSS], ['id' => 'DESC'], 100),
            'ingredients' => $this->em->getRepository(Ingredient::class)->findBy([], ['name' => 'ASC']),
            'units' => Unit::cases(), 'reasons' => LossReason::cases(),
        ]);
    }

    #[Route('', name: 'losses_new', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $this->guard($request);
        $this->attempt(function () use ($request) {
            $ingredient = $this->em->find(Ingredient::class, $request->request->getInt('ingredient')) ?? throw new BusinessRuleViolation('Choisissez un ingrédient.');
            $unit = Unit::tryFrom((string) $request->request->get('unit')) ?? throw new BusinessRuleViolation('Unité invalide.');
            $reason = LossReason::tryFrom((string) $request->request->get('reason')) ?? throw new BusinessRuleViolation('Choisissez un motif.');
            $comment = trim((string) $request->request->get('comment', ''));
            $this->stock->recordLoss($ingredient, $this->decimal($request, 'quantity'), $unit, $reason, '' === $comment ? null : mb_substr($comment, 0, 250), $this->getUser());
        }, 'Perte enregistrée.');

        return $this->redirectToRoute('losses');
    }
}
