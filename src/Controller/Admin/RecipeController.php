<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Dish;
use App\Entity\DishLine;
use App\Entity\Ingredient;
use App\Entity\Menu;
use App\Entity\MenuCourse;
use App\Entity\Preparation;
use App\Entity\PreparationLine;
use App\Enum\DishCategory;
use App\Enum\Unit;
use App\Exception\BusinessRuleViolation;
use App\Service\AvailabilityService;
use App\Service\RecipeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Back-office : plats, préparations de base et menus. */
#[Route('/admin')]
class RecipeController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RecipeResolver $resolver,
        private readonly AvailabilityService $availability,
    ) {
    }

    // ---- Plats -------------------------------------------------------------

    #[Route('/dishes', name: 'admin_dishes')]
    public function dishes(): Response
    {
        $rows = [];
        foreach ($this->em->getRepository(Dish::class)->findBy([], ['category' => 'ASC', 'name' => 'ASC']) as $dish) {
            try {
                $cost = $this->resolver->costOfDish($dish);
                $max = $this->availability->maxPortions($dish);
                $report = $this->resolver->allergensOfDish($dish);
                $error = null;
            } catch (BusinessRuleViolation $e) {
                $cost = null;
                $max = 0;
                $report = null;
                $error = $e->getMessage();
            }
            $rows[] = ['dish' => $dish, 'cost' => $cost, 'max' => $max, 'report' => $report, 'error' => $error];
        }

        return $this->render('admin/dishes.html.twig', ['rows' => $rows]);
    }

    #[Route('/dishes/new', name: 'admin_dish_new')]
    #[Route('/dishes/{id}/edit', name: 'admin_dish_edit')]
    public function dishForm(Request $request, ?Dish $dish = null): Response
    {
        $isNew = null === $dish;
        $dish ??= new Dish();
        $money = ['input' => 'string', 'scale' => 2, 'html5' => true, 'attr' => ['step' => '0.01', 'min' => 0]];
        $form = $this->createFormBuilder($dish)
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('category', EnumType::class, ['class' => DishCategory::class, 'label' => 'Catégorie', 'choice_label' => fn (DishCategory $c) => $c->label()])
            ->add('price', NumberType::class, $money + ['label' => 'Prix TTC (MAD)'])
            ->add('vatRate', NumberType::class, $money + ['label' => 'TVA (%)'])
            ->add('active', CheckboxType::class, ['label' => 'À la carte', 'required' => false])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($dish);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'Plat créé : ajoutez maintenant sa recette.' : 'Plat enregistré.');

            return $this->redirectToRoute('admin_dish_edit', ['id' => $dish->id]);
        }

        return $this->renderRecipe('dish', $dish, $form, $isNew ? 'Nouveau plat' : $dish->name);
    }

    // ---- Préparations de base ---------------------------------------------

    #[Route('/preparations', name: 'admin_preparations')]
    public function preparations(): Response
    {
        return $this->render('admin/preparations.html.twig', [
            'preparations' => $this->em->getRepository(Preparation::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/preparations/new', name: 'admin_preparation_new')]
    #[Route('/preparations/{id}/edit', name: 'admin_preparation_edit')]
    public function preparationForm(Request $request, ?Preparation $preparation = null): Response
    {
        $isNew = null === $preparation;
        $preparation ??= new Preparation();
        $form = $this->createFormBuilder($preparation)
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('yieldQuantity', NumberType::class, ['label' => 'Rendement (quantité produite)', 'input' => 'string', 'scale' => 3, 'html5' => true, 'attr' => ['step' => 'any', 'min' => 0]])
            ->add('yieldUnit', EnumType::class, ['class' => Unit::class, 'label' => 'Unité du rendement', 'choice_label' => fn (Unit $u) => $u->label()])
            ->add('notes', TextareaType::class, ['label' => 'Notes', 'required' => false, 'attr' => ['rows' => 2]])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($preparation);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'Préparation créée : ajoutez ses ingrédients.' : 'Préparation enregistrée.');

            return $this->redirectToRoute('admin_preparation_edit', ['id' => $preparation->id]);
        }

        return $this->renderRecipe('preparation', $preparation, $form, $isNew ? 'Nouvelle préparation' : $preparation->name);
    }

    /** Ajoute une ligne de recette à un plat ou à une préparation. */
    #[Route('/recipe/{kind}/{id}/lines', name: 'admin_recipe_line_add', requirements: ['kind' => 'dish|preparation', 'id' => '\d+'], methods: ['POST'])]
    public function addLine(string $kind, int $id, Request $request): RedirectResponse
    {
        $this->guard($request);
        $owner = $this->em->find('dish' === $kind ? Dish::class : Preparation::class, $id) ?? throw $this->createNotFoundException();
        $this->attempt(function () use ($kind, $owner, $request) {
            [$type, $cid] = array_pad(explode(':', (string) $request->request->get('component'), 2), 2, '');
            $component = 'i' === $type ? $this->em->find(Ingredient::class, (int) $cid) : ('p' === $type ? $this->em->find(Preparation::class, (int) $cid) : null);
            if (null === $component) {
                throw new BusinessRuleViolation('Choisissez un ingrédient ou une préparation.');
            }
            $unit = Unit::tryFrom((string) $request->request->get('unit')) ?? throw new BusinessRuleViolation('Unité invalide.');
            $line = 'dish' === $kind ? new DishLine() : new PreparationLine();
            $line->quantity = $this->decimal($request, 'quantity');
            $line->unit = $unit;
            if ('dish' === $kind) {
                $line->dish = $owner;
                $component instanceof Ingredient ? $line->ingredient = $component : $line->preparation = $component;
            } else {
                $line->preparation = $owner;
                if ($component instanceof Preparation && $this->resolver->wouldCreateCycle($owner, $component)) {
                    throw new BusinessRuleViolation(\sprintf('Impossible : « %s » contient déjà (directement ou non) « %s » — cela créerait une boucle.', $component, $owner));
                }
                $component instanceof Ingredient ? $line->ingredient = $component : $line->subPreparation = $component;
            }
            $this->resolver->assertLineValid($line);
            $owner->lines->add($line);
            $this->em->persist($line);
            $this->em->flush();
        }, 'Ligne ajoutée.');

        return $this->redirectToRoute('dish' === $kind ? 'admin_dish_edit' : 'admin_preparation_edit', ['id' => $id]);
    }

    #[Route('/recipe/{kind}/lines/{id}/delete', name: 'admin_recipe_line_delete', requirements: ['kind' => 'dish|preparation', 'id' => '\d+'], methods: ['POST'])]
    public function deleteLine(string $kind, int $id, Request $request): RedirectResponse
    {
        $this->guard($request);
        $line = $this->em->find('dish' === $kind ? DishLine::class : PreparationLine::class, $id) ?? throw $this->createNotFoundException();
        $owner = 'dish' === $kind ? $line->dish : $line->preparation;
        $owner->lines->removeElement($line);
        $this->em->flush();
        $this->addFlash('success', 'Ligne supprimée.');

        return $this->redirectToRoute('dish' === $kind ? 'admin_dish_edit' : 'admin_preparation_edit', ['id' => $owner->id]);
    }

    private function renderRecipe(string $kind, Dish|Preparation $owner, $form, string $title): Response
    {
        $info = ['error' => null];
        if (null !== $owner->id) {
            try {
                if ($owner instanceof Dish) {
                    $info += [
                        'cost' => $this->resolver->costOfDish($owner),
                        'max' => $this->availability->maxPortions($owner),
                        'report' => $this->resolver->allergensOfDish($owner),
                        'missing' => $this->availability->missingIngredients($owner),
                    ];
                }
            } catch (BusinessRuleViolation $e) {
                $info['error'] = $e->getMessage();
            }
        }
        $preparations = array_filter(
            $this->em->getRepository(Preparation::class)->findBy([], ['name' => 'ASC']),
            fn (Preparation $p) => $p !== $owner,
        );

        return $this->render('admin/recipe.html.twig', [
            'kind' => $kind, 'owner' => $owner, 'form' => $form, 'title' => $title, 'info' => $info,
            'ingredients' => $this->em->getRepository(Ingredient::class)->findBy([], ['name' => 'ASC']),
            'preparations' => $preparations, 'units' => Unit::cases(),
        ]);
    }

    // ---- Menus -------------------------------------------------------------

    #[Route('/menus', name: 'admin_menus')]
    public function menus(): Response
    {
        $rows = [];
        foreach ($this->em->getRepository(Menu::class)->findBy([], ['name' => 'ASC']) as $menu) {
            $rows[] = ['menu' => $menu, 'available' => $this->availability->isMenuAvailable($menu)];
        }

        return $this->render('admin/menus.html.twig', ['rows' => $rows]);
    }

    #[Route('/menus/new', name: 'admin_menu_new')]
    #[Route('/menus/{id}/edit', name: 'admin_menu_edit')]
    public function menuForm(Request $request, ?Menu $menu = null): Response
    {
        $isNew = null === $menu;
        $menu ??= new Menu();
        $form = $this->createFormBuilder($menu)
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('price', NumberType::class, ['label' => 'Prix TTC de la formule (MAD)', 'input' => 'string', 'scale' => 2, 'html5' => true, 'attr' => ['step' => '0.01', 'min' => 0]])
            ->add('active', CheckboxType::class, ['label' => 'Proposé', 'required' => false])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($menu);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'Menu créé : ajoutez ses temps (entrée, plat, dessert…).' : 'Menu enregistré.');

            return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->id]);
        }

        return $this->render('admin/menu.html.twig', [
            'menu' => $menu, 'form' => $form, 'title' => $isNew ? 'Nouveau menu' : $menu->name,
            'dishes' => $this->em->getRepository(Dish::class)->findBy([], ['category' => 'ASC', 'name' => 'ASC']),
        ]);
    }

    #[Route('/menus/{id}/courses', name: 'admin_menu_course_add', methods: ['POST'])]
    public function addCourse(Menu $menu, Request $request): RedirectResponse
    {
        $this->guard($request);
        $this->attempt(function () use ($menu, $request) {
            $label = trim((string) $request->request->get('label'));
            if ('' === $label) {
                throw new BusinessRuleViolation('Donnez un nom au temps (ex. Entrée).');
            }
            $course = new MenuCourse();
            $course->menu = $menu;
            $course->label = mb_substr($label, 0, 60);
            $course->position = $menu->courses->count() + 1;
            $menu->courses->add($course);
            $this->applyChoices($course, $request);
            $this->em->persist($course);
            $this->em->flush();
        }, 'Temps ajouté.');

        return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->id]);
    }

    #[Route('/menu-courses/{id}', name: 'admin_menu_course_save', methods: ['POST'])]
    public function saveCourse(MenuCourse $course, Request $request): RedirectResponse
    {
        $this->guard($request);
        $this->applyChoices($course, $request);
        $this->em->flush();
        $this->addFlash('success', 'Choix enregistrés.');

        return $this->redirectToRoute('admin_menu_edit', ['id' => $course->menu->id]);
    }

    #[Route('/menu-courses/{id}/delete', name: 'admin_menu_course_delete', methods: ['POST'])]
    public function deleteCourse(MenuCourse $course, Request $request): RedirectResponse
    {
        $this->guard($request);
        $menu = $course->menu;
        $menu->courses->removeElement($course);
        $this->em->flush();
        $this->addFlash('success', 'Temps supprimé.');

        return $this->redirectToRoute('admin_menu_edit', ['id' => $menu->id]);
    }

    private function applyChoices(MenuCourse $course, Request $request): void
    {
        $course->dishes->clear();
        foreach ((array) $request->request->all('dishes') as $dishId) {
            if (null !== $dish = $this->em->find(Dish::class, (int) $dishId)) {
                $course->dishes->add($dish);
            }
        }
    }
}
