<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\DiningTable;
use App\Entity\Ingredient;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\Allergen;
use App\Enum\Dimension;
use App\Service\AllergenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** Back-office : ingrédients, fournisseurs, tables, utilisateurs. */
#[Route('/admin')]
class CatalogController extends BaseController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    // ---- Ingrédients -------------------------------------------------------

    #[Route('/ingredients', name: 'admin_ingredients')]
    public function ingredients(): Response
    {
        return $this->render('admin/ingredients.html.twig', [
            'ingredients' => $this->em->getRepository(Ingredient::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/ingredients/new', name: 'admin_ingredient_new')]
    #[Route('/ingredients/{id}/edit', name: 'admin_ingredient_edit')]
    public function ingredientForm(Request $request, ?Ingredient $ingredient = null): Response
    {
        $isNew = null === $ingredient;
        $ingredient ??= new Ingredient();
        $num = ['input' => 'string', 'scale' => 3, 'html5' => true, 'attr' => ['step' => 'any', 'min' => 0]];

        return $this->entityForm($this->em, $request, $ingredient, [
            'name' => [TextType::class, ['label' => 'Nom']],
            'dimension' => [EnumType::class, [
                'class' => Dimension::class, 'label' => 'Grandeur', 'choice_label' => fn (Dimension $d) => $d->label(),
                'disabled' => !$isNew, 'help' => $isNew ? 'Définit l\'unité de stock ; non modifiable ensuite.' : 'Non modifiable : le stock est déjà suivi dans cette unité.',
            ]],
            'minStock' => [NumberType::class, $num + ['label' => 'Seuil minimum (en g, ml ou pièces)']],
            'targetStock' => [NumberType::class, $num + ['label' => 'Stock cible (en g, ml ou pièces)']],
            'barcode' => [TextType::class, ['label' => 'Code-barres (EAN, optionnel)', 'required' => false]],
            'supplier' => [EntityType::class, ['class' => Supplier::class, 'choice_label' => 'name', 'label' => 'Fournisseur habituel', 'required' => false, 'placeholder' => '—']],
        ], $isNew ? 'Nouvel ingrédient' : 'Modifier « '.$ingredient->name.' »', 'admin_ingredients');
    }

    /** Déclaration manuelle des allergènes. */
    #[Route('/ingredients/{id}/allergens', name: 'admin_ingredient_allergens', methods: ['GET', 'POST'])]
    public function allergens(Ingredient $ingredient, Request $request, AllergenService $service): Response
    {
        if ($request->isMethod('POST')) {
            $this->guard($request);
            $pick = static fn (string $key): array => array_values(array_filter(array_map(Allergen::tryFrom(...), (array) $request->request->all($key))));
            $service->declareManually($ingredient, $pick('allergens'), $pick('traces'));
            $this->addFlash('success', 'Déclaration enregistrée : l\'information est considérée comme fiable.');

            return $this->redirectToRoute('admin_ingredients');
        }

        return $this->render('admin/allergens.html.twig', ['ingredient' => $ingredient, 'all' => Allergen::cases()]);
    }

    #[Route('/ingredients/{id}/off', name: 'admin_ingredient_off', methods: ['POST'])]
    public function refreshOff(Ingredient $ingredient, Request $request, AllergenService $service): RedirectResponse
    {
        $this->guard($request);
        $this->attempt(function () use ($ingredient, $service) {
            $this->addFlash('success', $service->refresh($ingredient));
        });

        return $this->redirectToRoute('admin_ingredients');
    }

    // ---- Fournisseurs ------------------------------------------------------

    #[Route('/suppliers', name: 'admin_suppliers')]
    public function suppliers(): Response
    {
        return $this->render('admin/list.html.twig', [
            'title' => 'Fournisseurs', 'rows' => $this->em->getRepository(Supplier::class)->findBy([], ['name' => 'ASC']),
            'columns' => ['Nom' => 'name', 'Contact' => 'contactName', 'Téléphone' => 'phone', 'E-mail' => 'email'],
            'new' => 'admin_supplier_new', 'edit' => 'admin_supplier_edit',
        ]);
    }

    #[Route('/suppliers/new', name: 'admin_supplier_new')]
    #[Route('/suppliers/{id}/edit', name: 'admin_supplier_edit')]
    public function supplierForm(Request $request, ?Supplier $supplier = null): Response
    {
        return $this->entityForm($this->em, $request, $supplier ?? new Supplier(), [
            'name' => [TextType::class, ['label' => 'Nom']],
            'contactName' => [TextType::class, ['label' => 'Contact', 'required' => false]],
            'phone' => [TextType::class, ['label' => 'Téléphone', 'required' => false]],
            'email' => [EmailType::class, ['label' => 'E-mail', 'required' => false]],
        ], $supplier ? 'Modifier le fournisseur' : 'Nouveau fournisseur', 'admin_suppliers');
    }

    // ---- Tables ------------------------------------------------------------

    #[Route('/tables', name: 'admin_tables')]
    public function tables(): Response
    {
        return $this->render('admin/list.html.twig', [
            'title' => 'Tables', 'rows' => $this->em->getRepository(DiningTable::class)->findBy([], ['id' => 'ASC']),
            'columns' => ['Nom' => 'label', 'Places' => 'seats'], 'new' => 'admin_table_new', 'edit' => 'admin_table_edit',
        ]);
    }

    #[Route('/tables/new', name: 'admin_table_new')]
    #[Route('/tables/{id}/edit', name: 'admin_table_edit')]
    public function tableForm(Request $request, ?DiningTable $table = null): Response
    {
        return $this->entityForm($this->em, $request, $table ?? new DiningTable(), [
            'label' => [TextType::class, ['label' => 'Nom (ex. T1)']],
            'seats' => [IntegerType::class, ['label' => 'Places']],
        ], $table ? 'Modifier la table' : 'Nouvelle table', 'admin_tables');
    }

    // ---- Utilisateurs ------------------------------------------------------

    #[Route('/users', name: 'admin_users')]
    public function users(): Response
    {
        $rows = $this->em->getRepository(User::class)->findBy([], ['displayName' => 'ASC']);

        return $this->render('admin/list.html.twig', [
            'title' => 'Utilisateurs', 'rows' => $rows,
            'columns' => ['Nom' => 'displayName', 'E-mail' => 'email', 'Rôle' => 'roleLabel'], 'new' => 'admin_user_new', 'edit' => null,
        ]);
    }

    #[Route('/users/new', name: 'admin_user_new')]
    public function userNew(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createFormBuilder($user)
            ->add('displayName', TextType::class, ['label' => 'Nom affiché'])
            ->add('email', EmailType::class, ['label' => 'E-mail'])
            ->add('role', ChoiceType::class, ['label' => 'Rôle', 'mapped' => false, 'choices' => ['Serveur' => User::ROLE_SERVER, 'Cuisine' => User::ROLE_KITCHEN, 'Gérant' => User::ROLE_MANAGER]])
            ->add('plain', PasswordType::class, ['label' => 'Mot de passe (8 caractères min.)', 'mapped' => false, 'constraints' => [new NotBlank(), new Length(min: 8)]])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->roles = [$form->get('role')->getData()];
            $user->password = $hasher->hashPassword($user, $form->get('plain')->getData());
            $this->em->persist($user);
            $this->em->flush();
            $this->addFlash('success', 'Utilisateur créé.');

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/form.html.twig', ['form' => $form, 'title' => 'Nouvel utilisateur', 'back' => 'admin_users']);
    }
}
