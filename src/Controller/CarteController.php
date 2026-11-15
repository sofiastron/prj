<?php

namespace App\Controller;

use App\Entity\Dish;
use App\Entity\Menu;
use App\Enum\DishCategory;
use App\Service\AvailabilityService;
use App\Service\RecipeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Carte publique : plats, disponibilité du moment et allergènes. */
class CarteController extends AbstractController
{
    #[Route('/carte', name: 'carte')]
    public function index(EntityManagerInterface $em, AvailabilityService $availability, RecipeResolver $resolver): Response
    {
        $sections = [];
        foreach (DishCategory::cases() as $category) {
            $items = [];
            foreach ($em->getRepository(Dish::class)->findBy(['category' => $category, 'active' => true], ['name' => 'ASC']) as $dish) {
                $items[] = ['dish' => $dish, 'available' => $availability->isAvailable($dish), 'allergens' => $resolver->allergensOfDish($dish)];
            }
            if ($items) {
                $sections[] = ['category' => $category, 'items' => $items];
            }
        }
        $menus = [];
        foreach ($em->getRepository(Menu::class)->findBy(['active' => true], ['name' => 'ASC']) as $menu) {
            $menus[] = ['menu' => $menu, 'available' => $availability->isMenuAvailable($menu)];
        }

        return $this->render('carte.html.twig', ['sections' => $sections, 'menus' => $menus]);
    }
}
