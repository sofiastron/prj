<?php

namespace App\DataFixtures;

use App\Entity\CustomerOrder;
use App\Entity\DiningTable;
use App\Entity\Dish;
use App\Entity\DishLine;
use App\Entity\Ingredient;
use App\Entity\Menu;
use App\Entity\MenuCourse;
use App\Entity\OrderLine;
use App\Entity\Preparation;
use App\Entity\PreparationLine;
use App\Entity\PurchaseLine;
use App\Entity\PurchaseOrder;
use App\Entity\StockMovement;
use App\Entity\Supplier;
use App\Entity\User;
use App\Enum\Allergen;
use App\Enum\AllergenStatus;
use App\Enum\Dimension;
use App\Enum\DishCategory;
use App\Enum\LineStatus;
use App\Enum\LossReason;
use App\Enum\MovementType;
use App\Enum\OrderStatus;
use App\Enum\PurchaseStatus;
use App\Enum\Unit;
use App\Service\Decimal;
use App\Service\OrderService;
use App\Service\RecipeResolver;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Jeu de démonstration : restaurant marocain de 60 couverts à Agadir. */
class AppFixtures extends Fixture
{
    public const PASSWORD = 'password';

    /** @var array<string, Ingredient> */
    private array $ingredients = [];
    /** @var array<string, Preparation> */
    private array $preparations = [];
    /** @var array<string, Dish> */
    private array $dishes = [];
    /** @var array<string, Supplier> */
    private array $suppliers = [];

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly OrderService $orders,
        private readonly RecipeResolver $resolver,
    ) {
    }

    public function load(ObjectManager $om): void
    {
        mt_srand(2026);
        $users = $this->users($om);
        $this->tables($om);
        $this->suppliers($om);
        $this->ingredients($om);
        $om->flush();
        $this->preparations($om);
        $this->dishes($om);
        $this->menus($om);
        $om->flush();
        $this->history($om, $users['serveur']);
        $om->flush();
        $this->openOrders($om, $users['serveur']);
    }

    /** @return array<string, User> */
    private function users(ObjectManager $om): array
    {
        $users = [];
        foreach ([
            'gerant' => ['gerant@resto.test', 'Karim (gérant)', User::ROLE_MANAGER],
            'serveur' => ['serveur@resto.test', 'Salma (serveuse)', User::ROLE_SERVER],
            'serveur2' => ['serveur2@resto.test', 'Youssef (serveur)', User::ROLE_SERVER],
            'cuisine' => ['cuisine@resto.test', 'Hassan (chef de cuisine)', User::ROLE_KITCHEN],
        ] as $key => [$email, $name, $role]) {
            $u = new User();
            $u->email = $email;
            $u->displayName = $name;
            $u->roles = [$role];
            $u->password = $this->hasher->hashPassword($u, self::PASSWORD);
            $om->persist($u);
            $users[$key] = $u;
        }

        return $users;
    }

    private function tables(ObjectManager $om): void
    {
        $seats = [2, 2, 4, 4, 4, 4, 6, 6, 2, 4, 8, 4];   // 12 tables, 52 places + terrasse = 60 couverts
        foreach ($seats as $i => $n) {
            $t = new DiningTable();
            $t->label = 'T'.($i + 1);
            $t->seats = $n;
            $om->persist($t);
        }
    }

    private function suppliers(ObjectManager $om): void
    {
        foreach ([
            'marche' => ['Marché de gros d\'Agadir', 'M. Benali', '0528 22 10 01'],
            'boucherie' => ['Boucherie Atlas', 'M. Idrissi', '0528 84 20 20'],
            'poisson' => ['Poissonnerie du Port', 'M. Ouhammou', '0528 84 33 44'],
            'epicerie' => ['Souss Distribution (épicerie)', 'Mme Alaoui', '0528 30 40 50'],
            'laiterie' => ['Laiterie Centrale Souss', 'M. Tazi', '0528 31 12 12'],
            'boissons' => ['Boissons du Souss', 'M. Amrani', '0528 25 60 60'],
        ] as $key => [$name, $contact, $phone]) {
            $s = new Supplier();
            $s->name = $name;
            $s->contactName = $contact;
            $s->phone = $phone;
            $om->persist($s);
            $this->suppliers[$key] = $s;
        }
    }

    private function ingredients(ObjectManager $om): void
    {
        $G = Allergen::GLUTEN;
        $M = Allergen::MILK;
        $E = Allergen::EGGS;
        $N = Allergen::NUTS;
        $S = Allergen::SESAME;
        $F = Allergen::FISH;
        $C = Allergen::CRUSTACEANS;
        $MO = Allergen::MOLLUSCS;
        $CE = Allergen::CELERY;
        // nom => [fournisseur, dimension, unité du prix, prix MAD, stock (même unité), seuil min, cible, allergènes|null (null = inconnu), code-barres]
        $rows = [
            // Légumes et fruits
            'Tomate' => ['marche', Dimension::MASS, Unit::KG, 6, 25, 8, 30, []],
            'Oignon' => ['marche', Dimension::MASS, Unit::KG, 4, 20, 6, 25, []],
            'Carotte' => ['marche', Dimension::MASS, Unit::KG, 4, 15, 5, 20, []],
            'Courgette' => ['marche', Dimension::MASS, Unit::KG, 5, 12, 4, 15, []],
            'Pomme de terre' => ['marche', Dimension::MASS, Unit::KG, 4, 25, 8, 30, []],
            'Poivron' => ['marche', Dimension::MASS, Unit::KG, 8, 8, 3, 10, []],
            'Aubergine' => ['marche', Dimension::MASS, Unit::KG, 6, 10, 3, 12, []],
            'Navet' => ['marche', Dimension::MASS, Unit::KG, 4, 8, 3, 10, []],
            'Potiron' => ['marche', Dimension::MASS, Unit::KG, 5, 8, 3, 10, []],
            'Concombre' => ['marche', Dimension::MASS, Unit::KG, 5, 6, 2, 8, []],
            'Citron' => ['marche', Dimension::MASS, Unit::KG, 8, 6, 2, 8, []],
            'Orange' => ['marche', Dimension::MASS, Unit::KG, 5, 30, 8, 40, []],
            'Coriandre' => ['marche', Dimension::MASS, Unit::KG, 20, 2, 0.5, 3, []],
            'Persil' => ['marche', Dimension::MASS, Unit::KG, 20, 2, 0.5, 3, []],
            'Menthe' => ['marche', Dimension::MASS, Unit::KG, 25, 2, 0.5, 3, []],
            'Ail' => ['marche', Dimension::MASS, Unit::KG, 25, 2, 0.5, 3, []],
            'Citron confit' => ['marche', Dimension::COUNT, Unit::PIECE, 3, 30, 10, 40, []],
            'Olives violettes' => ['marche', Dimension::MASS, Unit::KG, 30, 3, 1, 4, []],
            'Pruneaux' => ['marche', Dimension::MASS, Unit::KG, 35, 3, 1, 4, []],
            // Viandes
            'Poulet fermier' => ['boucherie', Dimension::MASS, Unit::KG, 38, 20, 6, 25, []],
            'Épaule d\'agneau' => ['boucherie', Dimension::MASS, Unit::KG, 110, 12, 4, 15, []],
            'Boeuf haché' => ['boucherie', Dimension::MASS, Unit::KG, 85, 10, 3, 12, []],
            'Filet de boeuf' => ['boucherie', Dimension::MASS, Unit::KG, 150, 6, 2, 8, []],
            'Merguez' => ['boucherie', Dimension::MASS, Unit::KG, 70, 5, 2, 6, []],
            // Poissons et crustacés
            'Dorade' => ['poisson', Dimension::COUNT, Unit::PIECE, 55, 12, 4, 15, [$F]],
            'Crevettes' => ['poisson', Dimension::MASS, Unit::KG, 120, 6, 2, 8, [$C]],
            'Calamars' => ['poisson', Dimension::MASS, Unit::KG, 90, 4, 1.5, 5, [$MO]],
            // Épicerie
            'Semoule de couscous' => ['epicerie', Dimension::MASS, Unit::KG, 12, 25, 8, 30, [$G]],
            'Farine' => ['epicerie', Dimension::MASS, Unit::KG, 8, 15, 5, 20, [$G]],
            'Riz' => ['epicerie', Dimension::MASS, Unit::KG, 14, 15, 5, 20, []],
            'Pois chiches' => ['epicerie', Dimension::MASS, Unit::KG, 16, 10, 3, 12, []],
            'Lentilles' => ['epicerie', Dimension::MASS, Unit::KG, 18, 8, 3, 10, []],
            'Vermicelles' => ['epicerie', Dimension::MASS, Unit::KG, 15, 5, 2, 6, [$G]],
            'Huile d\'olive' => ['epicerie', Dimension::VOLUME, Unit::L, 70, 12, 4, 15, []],
            'Huile de tournesol' => ['epicerie', Dimension::VOLUME, Unit::L, 20, 20, 6, 25, []],
            'Sel' => ['epicerie', Dimension::MASS, Unit::KG, 4, 5, 1, 6, []],
            'Poivre noir' => ['epicerie', Dimension::MASS, Unit::KG, 120, 1, 0.3, 1.5, []],
            'Cumin' => ['epicerie', Dimension::MASS, Unit::KG, 90, 1, 0.3, 1.5, []],
            'Paprika' => ['epicerie', Dimension::MASS, Unit::KG, 80, 1, 0.3, 1.5, []],
            'Gingembre' => ['epicerie', Dimension::MASS, Unit::KG, 70, 1, 0.3, 1.5, []],
            'Curcuma' => ['epicerie', Dimension::MASS, Unit::KG, 100, 1, 0.3, 1.5, []],
            'Cannelle' => ['epicerie', Dimension::MASS, Unit::KG, 120, 1, 0.2, 1, []],
            'Safran' => ['epicerie', Dimension::MASS, Unit::KG, 20000, 0.05, 0.02, 0.08, []],
            'Amandes' => ['epicerie', Dimension::MASS, Unit::KG, 110, 4, 1, 5, [$N]],
            'Miel' => ['epicerie', Dimension::MASS, Unit::KG, 60, 3, 1, 4, []],
            'Sucre' => ['epicerie', Dimension::MASS, Unit::KG, 8, 15, 5, 20, []],
            'Feuilles de brick' => ['epicerie', Dimension::COUNT, Unit::PIECE, 1.2, 100, 30, 150, [$G]],
            'Graines de sésame' => ['epicerie', Dimension::MASS, Unit::KG, 45, 1, 0.3, 1.5, [$S]],
            'Thé vert' => ['epicerie', Dimension::MASS, Unit::KG, 120, 2, 0.5, 2.5, []],
            'Céleri' => ['marche', Dimension::MASS, Unit::KG, 8, 3, 1, 4, [$CE]],
            // Produits laitiers et oeufs
            'Beurre' => ['laiterie', Dimension::MASS, Unit::KG, 75, 6, 2, 8, [$M]],
            'Lait' => ['laiterie', Dimension::VOLUME, Unit::L, 8, 20, 6, 25, [$M]],
            'Crème fraîche' => ['laiterie', Dimension::VOLUME, Unit::L, 40, 4, 1, 5, [$M]],
            'Oeufs' => ['laiterie', Dimension::COUNT, Unit::PIECE, 1.6, 120, 36, 180, [$E]],
            'Fromage frais' => ['laiterie', Dimension::MASS, Unit::KG, 60, 4, 1.5, 5, [$M]],
            // Boissons
            'Eau minérale 50cl' => ['boissons', Dimension::COUNT, Unit::PIECE, 3, 120, 40, 150, []],
            'Coca-Cola 33cl' => ['boissons', Dimension::COUNT, Unit::PIECE, 5, 96, 36, 120, [], '5449000000996'],
            // Produits du commerce : la fiche allergène est à compléter (démonstration de l'avertissement)
            'Spaghetti Barilla n°5' => ['epicerie', Dimension::MASS, Unit::KG, 20, 10, 3, 12, [$G], '8076800195057'],
            'Nutella' => ['epicerie', Dimension::MASS, Unit::KG, 90, 2, 0.5, 3, [$M, $N, Allergen::SOYBEANS], '3017620422003'],
            'Harissa du commerce' => ['epicerie', Dimension::MASS, Unit::KG, 40, 2, 0.5, 3, null],
            'Ras el hanout du souk' => ['epicerie', Dimension::MASS, Unit::KG, 150, 1, 0.3, 1.5, null],
        ];
        foreach ($rows as $name => $r) {
            [$sup, $dim, $unit, $price, $stock, $min, $target, $allergens] = $r;
            $i = new Ingredient();
            $i->name = $name;
            $i->dimension = $dim;
            $i->supplier = $this->suppliers[$sup];
            $i->averageCost = Decimal::round($unit->priceToBase((string) $price), 6);
            $i->stockQuantity = Decimal::round($unit->toBase((string) $stock), 3);
            $i->minStock = Decimal::round($unit->toBase((string) $min), 3);
            $i->targetStock = Decimal::round($unit->toBase((string) $target), 3);
            $i->barcode = $r[8] ?? null;
            if (null === $allergens) {
                $i->allergenStatus = AllergenStatus::UNKNOWN;
            } else {
                $i->allergens = array_map(fn (Allergen $a) => $a->value, $allergens);
                $i->allergenStatus = AllergenStatus::KNOWN;
                $i->allergenSource = isset($r[8]) ? 'off' : 'manual';
            }
            $om->persist($i);
            $this->ingredients[$name] = $i;
        }
    }

    private function preparations(ObjectManager $om): void
    {
        $defs = [
            'Sauce tomate maison' => [2, Unit::KG, [['Tomate', 3, Unit::KG], ['Oignon', 300, Unit::G], ['Ail', 50, Unit::G], ['Huile d\'olive', 15, Unit::CL], ['Sel', 20, Unit::G]]],
            'Fond de volaille' => [3, Unit::L, [['Poulet fermier', 1.5, Unit::KG], ['Carotte', 300, Unit::G], ['Oignon', 300, Unit::G], ['Céleri', 100, Unit::G], ['Sel', 30, Unit::G]]],
            'Chermoula' => [0.5, Unit::KG, [['Coriandre', 100, Unit::G], ['Persil', 100, Unit::G], ['Ail', 50, Unit::G], ['Cumin', 10, Unit::G], ['Paprika', 15, Unit::G], ['Citron', 100, Unit::G], ['Huile d\'olive', 12, Unit::CL]]],
            'Pâte à tarte' => [0.8, Unit::KG, [['Farine', 500, Unit::G], ['Beurre', 250, Unit::G], ['Oeufs', 2, Unit::PIECE], ['Sel', 5, Unit::G]]],
            // Préparation composée de deux autres : démontre le dépliage récursif.
            'Sauce tajine citron-olives' => [1, Unit::L, [['@Fond de volaille', 60, Unit::CL], ['@Chermoula', 100, Unit::G], ['Citron confit', 3, Unit::PIECE], ['Olives violettes', 100, Unit::G], ['Safran', 0.2, Unit::G], ['Curcuma', 5, Unit::G]]],
        ];
        foreach ($defs as $name => [$yield, $yieldUnit, $lines]) {
            $p = new Preparation();
            $p->name = $name;
            $p->yieldQuantity = (string) $yield;
            $p->yieldUnit = $yieldUnit;
            foreach ($lines as [$comp, $qty, $unit]) {
                $l = new PreparationLine();
                $l->preparation = $p;
                $l->quantity = (string) $qty;
                $l->unit = $unit;
                str_starts_with($comp, '@') ? $l->subPreparation = $this->preparations[substr($comp, 1)] : $l->ingredient = $this->ingredients[$comp];
                $p->lines->add($l);
            }
            $om->persist($p);
            $this->preparations[$name] = $p;
        }
    }

    private function dishes(ObjectManager $om): void
    {
        $S = DishCategory::STARTER;
        $M = DishCategory::MAIN;
        $D = DishCategory::DESSERT;
        $B = DishCategory::DRINK;
        $defs = [
            'Harira' => [$S, 35, 'Soupe traditionnelle aux lentilles, pois chiches et coriandre', [['Tomate', 100, Unit::G], ['Lentilles', 30, Unit::G], ['Pois chiches', 30, Unit::G], ['Oignon', 40, Unit::G], ['Coriandre', 10, Unit::G], ['Farine', 10, Unit::G], ['Vermicelles', 15, Unit::G]]],
            'Salade marocaine' => [$S, 30, 'Tomates, concombre, oignon et coriandre', [['Tomate', 120, Unit::G], ['Concombre', 80, Unit::G], ['Oignon', 30, Unit::G], ['Coriandre', 5, Unit::G], ['Huile d\'olive', 2, Unit::CL]]],
            'Zaalouk' => [$S, 35, 'Caviar d\'aubergines et tomates', [['Aubergine', 200, Unit::G], ['Tomate', 100, Unit::G], ['Ail', 5, Unit::G], ['Huile d\'olive', 3, Unit::CL], ['Paprika', 2, Unit::G]]],
            'Briouates aux crevettes' => [$S, 55, 'Feuilletés croustillants aux crevettes', [['Feuilles de brick', 3, Unit::PIECE], ['Crevettes', 100, Unit::G], ['Fromage frais', 30, Unit::G], ['Huile de tournesol', 5, Unit::CL]]],
            'Pastilla au poulet' => [$S, 70, 'Feuilletée au poulet, amandes et cannelle', [['Feuilles de brick', 4, Unit::PIECE], ['Poulet fermier', 120, Unit::G], ['Amandes', 30, Unit::G], ['Oeufs', 1, Unit::PIECE], ['Cannelle', 2, Unit::G], ['Sucre', 10, Unit::G], ['Beurre', 15, Unit::G]]],
            'Tajine de poulet aux olives et citron confit' => [$M, 85, 'Le grand classique, sauce citron-olives', [['Poulet fermier', 300, Unit::G], ['@Sauce tajine citron-olives', 25, Unit::CL], ['Oignon', 80, Unit::G], ['Pomme de terre', 100, Unit::G]]],
            'Tajine d\'agneau aux pruneaux' => [$M, 110, 'Agneau fondant, pruneaux, amandes et miel', [['Épaule d\'agneau', 300, Unit::G], ['Pruneaux', 60, Unit::G], ['Amandes', 20, Unit::G], ['Miel', 20, Unit::G], ['Oignon', 80, Unit::G], ['Cannelle', 2, Unit::G], ['Gingembre', 2, Unit::G], ['Graines de sésame', 3, Unit::G]]],
            'Couscous royal' => [$M, 120, 'Semoule, agneau, poulet, merguez et sept légumes', [['Semoule de couscous', 120, Unit::G], ['Épaule d\'agneau', 150, Unit::G], ['Poulet fermier', 150, Unit::G], ['Merguez', 80, Unit::G], ['Carotte', 80, Unit::G], ['Courgette', 80, Unit::G], ['Navet', 60, Unit::G], ['Potiron', 60, Unit::G], ['Pois chiches', 40, Unit::G], ['Beurre', 15, Unit::G], ['Ras el hanout du souk', 3, Unit::G]]],
            'Couscous aux sept légumes' => [$M, 75, 'Végétarien', [['Semoule de couscous', 120, Unit::G], ['Carotte', 100, Unit::G], ['Courgette', 100, Unit::G], ['Navet', 70, Unit::G], ['Potiron', 70, Unit::G], ['Pois chiches', 50, Unit::G], ['Beurre', 15, Unit::G], ['Curcuma', 2, Unit::G]]],
            'Brochettes de boeuf' => [$M, 90, 'Filet de boeuf grillé, riz et poivrons', [['Filet de boeuf', 220, Unit::G], ['Riz', 120, Unit::G], ['Poivron', 60, Unit::G], ['Cumin', 2, Unit::G], ['Huile d\'olive', 2, Unit::CL]]],
            'Tajine kefta aux oeufs' => [$M, 70, 'Boulettes de boeuf, sauce tomate et oeufs', [['Boeuf haché', 200, Unit::G], ['@Sauce tomate maison', 200, Unit::G], ['Oeufs', 2, Unit::PIECE], ['Persil', 8, Unit::G], ['Cumin', 2, Unit::G], ['Harissa du commerce', 5, Unit::G]]],
            'Dorade royale grillée' => [$M, 130, 'Dorade entière grillée, chermoula', [['Dorade', 1, Unit::PIECE], ['@Chermoula', 40, Unit::G], ['Riz', 100, Unit::G], ['Citron', 40, Unit::G]]],
            'Crevettes à la chermoula' => [$M, 140, 'Crevettes sautées, chermoula et riz', [['Crevettes', 250, Unit::G], ['@Chermoula', 50, Unit::G], ['Riz', 100, Unit::G], ['Huile d\'olive', 3, Unit::CL]]],
            'Calamars frits' => [$M, 95, 'Calamars croustillants, sauce citron', [['Calamars', 250, Unit::G], ['Farine', 40, Unit::G], ['Huile de tournesol', 15, Unit::CL], ['Citron', 30, Unit::G], ['Pomme de terre', 150, Unit::G]]],
            'Spaghetti sauce tomate' => [$M, 60, 'Pâtes et sauce tomate maison', [['Spaghetti Barilla n°5', 120, Unit::G], ['@Sauce tomate maison', 150, Unit::G], ['Huile d\'olive', 1, Unit::CL]]],
            'Pastilla au lait' => [$D, 40, 'Crème légère, amandes et feuilles croustillantes', [['Feuilles de brick', 2, Unit::PIECE], ['Lait', 15, Unit::CL], ['Amandes', 15, Unit::G], ['Sucre', 20, Unit::G], ['Cannelle', 1, Unit::G]]],
            'Salade d\'oranges à la cannelle' => [$D, 30, 'Oranges fraîches, cannelle', [['Orange', 250, Unit::G], ['Cannelle', 1, Unit::G], ['Sucre', 5, Unit::G]]],
            'Crème caramel' => [$D, 30, 'Lait, oeufs et caramel', [['Lait', 15, Unit::CL], ['Oeufs', 1, Unit::PIECE], ['Sucre', 30, Unit::G]]],
            'Crêpe au Nutella' => [$D, 35, 'Crêpe fine fourrée', [['Farine', 40, Unit::G], ['Lait', 10, Unit::CL], ['Oeufs', 1, Unit::PIECE], ['Nutella', 30, Unit::G], ['Beurre', 5, Unit::G]]],
            'Thé à la menthe' => [$B, 15, 'Théière pour une personne', [['Thé vert', 5, Unit::G], ['Menthe', 20, Unit::G], ['Sucre', 25, Unit::G]]],
            'Eau minérale 50cl' => [$B, 10, '', [['Eau minérale 50cl', 1, Unit::PIECE]]],
            'Coca-Cola 33cl' => [$B, 15, '', [['Coca-Cola 33cl', 1, Unit::PIECE]]],
            'Jus d\'orange pressé' => [$B, 25, 'Pressé minute', [['Orange', 500, Unit::G]]],
        ];
        foreach ($defs as $name => [$cat, $price, $desc, $lines]) {
            $d = new Dish();
            $d->name = $name;
            $d->category = $cat;
            $d->price = number_format($price, 2, '.', '');
            $d->description = '' === $desc ? null : $desc;
            foreach ($lines as [$comp, $qty, $unit]) {
                $l = new DishLine();
                $l->dish = $d;
                $l->quantity = (string) $qty;
                $l->unit = $unit;
                str_starts_with($comp, '@') ? $l->preparation = $this->preparations[substr($comp, 1)] : $l->ingredient = $this->ingredients[$comp];
                $d->lines->add($l);
            }
            $om->persist($d);
            $this->dishes[$name] = $d;
        }
    }

    private function menus(ObjectManager $om): void
    {
        $defs = [
            'Menu Découverte' => [150, [
                'Entrée' => ['Harira', 'Salade marocaine', 'Zaalouk'],
                'Plat' => ['Tajine de poulet aux olives et citron confit', 'Couscous aux sept légumes', 'Tajine kefta aux oeufs'],
                'Dessert' => ['Pastilla au lait', 'Salade d\'oranges à la cannelle', 'Crème caramel'],
            ]],
            'Menu de la Mer' => [190, [
                'Entrée' => ['Briouates aux crevettes', 'Salade marocaine'],
                'Plat' => ['Dorade royale grillée', 'Crevettes à la chermoula', 'Calamars frits'],
                'Dessert' => ['Salade d\'oranges à la cannelle', 'Crème caramel'],
            ]],
        ];
        foreach ($defs as $name => [$price, $courses]) {
            $m = new Menu();
            $m->name = $name;
            $m->price = number_format($price, 2, '.', '');
            $pos = 1;
            foreach ($courses as $label => $dishNames) {
                $c = new MenuCourse();
                $c->menu = $m;
                $c->label = $label;
                $c->position = $pos++;
                foreach ($dishNames as $dn) {
                    $c->dishes->add($this->dishes[$dn]);
                }
                $m->courses->add($c);
            }
            $om->persist($m);
        }
    }

    /** Deux semaines d'historique de ventes (commandes payées) et quelques pertes : alimente le tableau de bord. */
    private function history(ObjectManager $om, User $server): void
    {
        $tables = $om->getRepository(DiningTable::class)->findAll();
        $dishes = array_values($this->dishes);
        // Pondération : certains plats se vendent mieux.
        $weights = [];
        foreach ($dishes as $i => $d) {
            $weights[$i] = match ($d->name) {
                'Tajine de poulet aux olives et citron confit', 'Couscous royal', 'Thé à la menthe' => 8,
                'Salade marocaine', 'Harira', 'Eau minérale 50cl', 'Coca-Cola 33cl', 'Pastilla au lait' => 5,
                'Crevettes à la chermoula', 'Dorade royale grillée', 'Brochettes de boeuf' => 3,
                default => 2,
            };
        }
        $pick = function () use ($weights): int {
            $r = mt_rand(1, array_sum($weights));
            foreach ($weights as $i => $w) {
                if (($r -= $w) <= 0) {
                    return $i;
                }
            }

            return 0;
        };

        for ($day = 14; $day >= 1; --$day) {
            $orders = mt_rand(8, 16) + (\in_array((int) date('N', strtotime("-$day days")), [5, 6, 7], true) ? 6 : 0);
            for ($n = 0; $n < $orders; ++$n) {
                $at = (new \DateTimeImmutable("-$day days"))->setTime(mt_rand(12, 21), mt_rand(0, 59));
                $o = new CustomerOrder();
                $o->table = $tables[array_rand($tables)];
                $o->server = $server;
                $o->covers = mt_rand(1, 4);
                $o->status = OrderStatus::PAID;
                $o->createdAt = $at;
                $o->paidAt = $at->modify('+'.mt_rand(40, 90).' minutes');
                $om->persist($o);
                for ($k = 0, $items = $o->covers + mt_rand(0, 2); $k < $items; ++$k) {
                    $dish = $dishes[$pick()];
                    $l = new OrderLine();
                    $l->order = $o;
                    $l->dish = $dish;
                    $l->dishName = (string) $dish->name;
                    $l->quantity = 1;
                    $l->unitPrice = $dish->price;
                    $l->vatRate = $dish->vatRate;
                    $l->unitCost = $this->resolver->costOfDish($dish);
                    $l->status = LineStatus::SERVED;
                    $l->createdAt = $at;
                    $l->startedAt = $at->modify('+3 minutes');
                    $l->readyAt = $at->modify('+14 minutes');
                    $l->servedAt = $at->modify('+16 minutes');
                    $o->lines->add($l);
                    $om->persist($l);
                }
            }
        }

        // Quelques pertes enregistrées.
        foreach ([
            ['Tomate', 1500, LossReason::EXPIRY, 'lot abîmé', 3],
            ['Crevettes', 400, LossReason::KITCHEN_ERROR, 'brûlées au service', 5],
            ['Lait', 2000, LossReason::EXPIRY, 'DLC dépassée', 8],
            ['Oeufs', 6, LossReason::BREAKAGE, 'plateau tombé', 2],
        ] as [$name, $qty, $reason, $comment, $ago]) {
            $ing = $this->ingredients[$name];
            $m = new StockMovement();
            $m->ingredient = $ing;
            $m->type = MovementType::LOSS;
            $m->quantity = '-'.number_format($qty, 3, '.', '');
            $m->unitCost = $ing->averageCost;
            $m->lossReason = $reason;
            $m->comment = $comment;
            $m->createdAt = new \DateTimeImmutable("-$ago days");
            $om->persist($m);
        }

        // Une livraison reçue (historique d'achat).
        $po = new PurchaseOrder();
        $po->supplier = $this->suppliers['marche'];
        $po->status = PurchaseStatus::RECEIVED;
        $po->createdAt = new \DateTimeImmutable('-6 days');
        $po->orderedAt = new \DateTimeImmutable('-6 days');
        $po->receivedAt = new \DateTimeImmutable('-5 days');
        foreach ([['Tomate', 20, Unit::KG, 6], ['Oignon', 15, Unit::KG, 4]] as [$n, $q, $u, $p]) {
            $pl = new PurchaseLine();
            $pl->purchaseOrder = $po;
            $pl->ingredient = $this->ingredients[$n];
            $pl->quantity = (string) $q;
            $pl->unit = $u;
            $pl->unitPrice = (string) $p;
            $po->lines->add($pl);
        }
        $om->persist($po);

        // Quelques ingrédients volontairement bas : suggestions de réapprovisionnement.
        $this->ingredients['Crevettes']->stockQuantity = '1500.000';
        $this->ingredients['Dorade']->stockQuantity = '3.000';
        $this->ingredients['Safran']->stockQuantity = '0.800';
        $this->ingredients['Feuilles de brick']->stockQuantity = '25.000';
    }

    /** Deux commandes en cours (passées par le vrai service : stocks décrémentés) pour la démonstration de l'écran cuisine. */
    private function openOrders(ObjectManager $om, User $server): void
    {
        $tables = $om->getRepository(DiningTable::class)->findBy([], ['id' => 'ASC']);
        $o1 = $this->orders->open($tables[2], $server, 3);
        $this->orders->addDish($o1, $this->dishes['Harira'], 2, null, $server);
        $this->orders->addDish($o1, $this->dishes['Tajine de poulet aux olives et citron confit'], 2, 'sans olives pour un convive', $server);
        $this->orders->addDish($o1, $this->dishes['Couscous royal'], 1, null, $server);
        $o2 = $this->orders->open($tables[4], $server, 2);
        $this->orders->addDish($o2, $this->dishes['Crevettes à la chermoula'], 1, null, $server);
        $this->orders->addDish($o2, $this->dishes['Thé à la menthe'], 2, null, $server);
        // La première entrée est déjà en préparation côté cuisine.
        $first = $o1->lines->first();
        $this->orders->advance($first, LineStatus::PREPARING);
    }
}
