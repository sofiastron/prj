<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $auth): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', ['last' => $auth->getLastUsername(), 'error' => $auth->getLastAuthenticationError()]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepté par le pare-feu.');
    }

    /** Page d'accueil : chacun arrive sur son écran de travail. */
    #[Route('/', name: 'app_home')]
    public function home(): RedirectResponse
    {
        return match (true) {
            $this->isGranted(User::ROLE_MANAGER) => $this->redirectToRoute('app_dashboard'),
            $this->isGranted(User::ROLE_KITCHEN) => $this->redirectToRoute('kitchen_index'),
            default => $this->redirectToRoute('service_tables'),
        };
    }
}
