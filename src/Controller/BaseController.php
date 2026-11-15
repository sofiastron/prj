<?php

namespace App\Controller;

use App\Exception\BusinessRuleViolation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseController extends AbstractController
{
    /** Contrôle du jeton CSRF des formulaires POST (jeton « app » généré par csrf_token('app')). */
    protected function guard(Request $request): void
    {
        if (!$this->isCsrfTokenValid('app', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide : rechargez la page.');
        }
    }

    /** Exécute une action métier ; une règle violée devient un message d'erreur, pas une page 500. */
    protected function attempt(callable $action, ?string $success = null): bool
    {
        try {
            $action();
        } catch (BusinessRuleViolation $e) {
            $this->addFlash('error', $e->getMessage());

            return false;
        }
        if (null !== $success) {
            $this->addFlash('success', $success);
        }

        return true;
    }

    /** Lit un nombre décimal saisi (virgule acceptée) ; lève une règle métier s'il est invalide. */
    protected function decimal(Request $request, string $key, bool $allowNegative = false): string
    {
        $raw = str_replace([',', ' '], ['.', ''], trim((string) $request->request->get($key, '')));
        if (!preg_match($allowNegative ? '/^-?\d+(\.\d+)?$/' : '/^\d+(\.\d+)?$/', $raw)) {
            throw new BusinessRuleViolation('Nombre invalide.');
        }

        return $raw;
    }

    /**
     * Page de formulaire générique pour créer / modifier une entité.
     *
     * @param array<string, array{0: class-string, 1?: array<string, mixed>}> $fields
     */
    protected function entityForm(EntityManagerInterface $em, Request $request, object $entity, array $fields, string $title, string $backRoute, ?callable $afterSave = null): Response
    {
        $builder = $this->createFormBuilder($entity);
        foreach ($fields as $name => $def) {
            $builder->add($name, $def[0], $def[1] ?? []);
        }
        $form = $builder->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entity);
            $em->flush();
            if (null !== $afterSave) {
                $afterSave($entity);
            }
            $this->addFlash('success', 'Enregistré.');

            return $this->redirectToRoute($backRoute);
        }

        return $this->render('admin/form.html.twig', ['form' => $form, 'title' => $title, 'back' => $backRoute]);
    }
}
