<?php

namespace App\Tests\Integration;

use App\Entity\Menu;
use App\Entity\MenuCourse;
use App\Entity\StockMovement;
use App\Enum\Dimension;
use App\Enum\LineStatus;
use App\Enum\LossReason;
use App\Enum\MovementType;
use App\Enum\OrderStatus;
use App\Enum\Unit;
use App\Exception\BusinessRuleViolation;
use App\Exception\InsufficientStockException;
use App\Service\AvailabilityService;
use App\Service\OrderService;
use App\Service\StockService;
use Doctrine\DBAL\Exception as DbalException;

/** Niveau 2 : les règles métier sur le stock et le cycle de vie des commandes. */
class StockRulesTest extends IntegrationTestCase
{
    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = $this->service(OrderService::class);
    }

    public function testServedDishDecrementsStockOfEveryIngredient(): void
    {
        $meat = $this->ingredient('Poulet', Dimension::MASS, '5000');
        $oil = $this->ingredient('Huile', Dimension::VOLUME, '1000');
        $tajine = $this->dish('Tajine', '80.00', [[$meat, '0.3', Unit::KG], [$oil, '4', Unit::CL]]);
        $order = $this->orders->open($this->table(), $this->user());

        $line = $this->orders->addDish($order, $tajine, 2);
        foreach ([LineStatus::PREPARING, LineStatus::READY, LineStatus::SERVED] as $status) {
            $this->orders->advance($line, $status);
        }

        $this->assertSame('4400.000', $this->refresh($meat)->stockQuantity, '5000 − 2×300 g');
        $this->assertSame('920.000', $this->refresh($oil)->stockQuantity, '1000 − 2×40 ml');
    }

    public function testPreparationIngredientsAreConsumedThroughTheDish(): void
    {
        $tomato = $this->ingredient('Tomate', Dimension::MASS, '10000');
        $sauce = $this->preparation('Sauce', '2', Unit::KG, [[$tomato, '3', Unit::KG]]);   // 3 kg → 2 kg de sauce
        $pasta = $this->dish('Pâtes sauce tomate', '60.00', [[$sauce, '200', Unit::G]]);     // 10 % de la préparation
        $order = $this->orders->open($this->table(), $this->user());

        $this->orders->addDish($order, $pasta);

        $this->assertSame('9700.000', $this->refresh($tomato)->stockQuantity, '300 g de tomates par assiette');
    }

    public function testDishBecomesUnavailableWhenAnIngredientRunsOut(): void
    {
        $fish = $this->ingredient('Dorade', Dimension::COUNT, '2');
        $dish = $this->dish('Dorade grillée', '120.00', [[$fish, '1', Unit::PIECE]]);
        $availability = $this->service(AvailabilityService::class);
        $order = $this->orders->open($this->table(), $this->user());

        $this->assertTrue($availability->isAvailable($dish));
        $this->assertSame(2, $availability->maxPortions($dish));

        $this->orders->addDish($order, $dish, 2);

        $this->assertFalse($availability->isAvailable($this->refresh($dish)), 'plus de dorade : le plat n\'est plus commandable');
        $this->assertSame('Dorade', $availability->missingIngredients($dish)[0]->name);
    }

    public function testOrderingAnUnavailableDishIsRejectedAndLeavesStockUntouched(): void
    {
        $meat = $this->ingredient('Agneau', Dimension::MASS, '500');
        $rice = $this->ingredient('Riz', Dimension::MASS, '10000');
        $dish = $this->dish('Méchoui', '150.00', [[$rice, '100', Unit::G], [$meat, '300', Unit::G]]);
        $order = $this->orders->open($this->table(), $this->user());
        $riceId = $rice->id;
        $meatId = $meat->id;

        try {
            $this->orders->addDish($order, $dish, 2);   // 600 g d'agneau demandés, 500 g en stock
            $this->fail('InsufficientStockException attendue');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('Agneau', $e->getMessage());
        }

        $this->em->clear();
        $this->assertSame('10000.000', $this->em->find(\App\Entity\Ingredient::class, $riceId)->stockQuantity, 'tout-ou-rien : le riz n\'est pas débité');
        $this->assertSame('500.000', $this->em->find(\App\Entity\Ingredient::class, $meatId)->stockQuantity);
        $this->assertSame(0, $this->em->getRepository(StockMovement::class)->count(['type' => MovementType::SALE]));
    }

    public function testDishRemovedFromTheCardCannotBeOrdered(): void
    {
        $rice = $this->ingredient('Riz', Dimension::MASS, '1000');
        $dish = $this->dish('Riz cantonais', '40.00', [[$rice, '100', Unit::G]], active: false);
        $order = $this->orders->open($this->table(), $this->user());

        $this->expectException(BusinessRuleViolation::class);
        $this->orders->addDish($order, $dish);
    }

    public function testStockCanNeverBecomeNegative(): void
    {
        $salt = $this->ingredient('Sel', Dimension::MASS, '100');
        $stock = $this->service(StockService::class);

        try {
            $stock->recordLoss($salt, '1', Unit::KG, LossReason::BREAKAGE);   // 1000 g perdus sur 100 g
            $this->fail('InsufficientStockException attendue');
        } catch (InsufficientStockException) {
        }

        $this->em->clear();
        $this->assertSame('100.000', $this->em->find(\App\Entity\Ingredient::class, $salt->id)->stockQuantity);
    }

    public function testDatabaseItselfRefusesNegativeStock(): void
    {
        $salt = $this->ingredient('Sel', Dimension::MASS, '100');

        $this->expectException(DbalException::class);
        $this->em->getConnection()->executeStatement('UPDATE ingredient SET stock_quantity = -1 WHERE id = ?', [$salt->id]);
    }

    public function testUnitsAreHandledProperly(): void
    {
        $milk = $this->ingredient('Lait', Dimension::VOLUME, '2000');   // 2 l
        $stock = $this->service(StockService::class);

        $stock->recordLoss($milk, '25', Unit::CL, LossReason::EXPIRY);   // 250 ml

        $this->assertSame('1750.000', $this->refresh($milk)->stockQuantity);

        $this->expectException(BusinessRuleViolation::class);
        $stock->recordLoss($milk, '100', Unit::G, LossReason::OTHER);   // des grammes de lait : refusé
    }

    public function testLossesAreRecordedWithReasonAndCost(): void
    {
        $cream = $this->ingredient('Crème', Dimension::VOLUME, '3000', '0.04');
        $stock = $this->service(StockService::class);

        $m = $stock->recordLoss($cream, '1', Unit::L, LossReason::KITCHEN_ERROR, 'sauce tranchée');

        $this->assertSame(MovementType::LOSS, $m->type);
        $this->assertSame(LossReason::KITCHEN_ERROR, $m->lossReason);
        $this->assertSame('-1000.000', $m->quantity);
        $this->assertSame('0.040000', $m->unitCost);
        $this->assertSame('2000.000', $this->refresh($cream)->stockQuantity);
    }

    public function testCancellingAPendingDishGivesStockBack(): void
    {
        $rice = $this->ingredient('Riz', Dimension::MASS, '1000');
        $dish = $this->dish('Riz', '40.00', [[$rice, '250', Unit::G]]);
        $order = $this->orders->open($this->table(), $this->user());
        $line = $this->orders->addDish($order, $dish, 2);
        $this->assertSame('500.000', $this->refresh($rice)->stockQuantity);

        $this->orders->cancelLine($line);

        $this->assertSame('1000.000', $this->refresh($rice)->stockQuantity);
        $this->assertSame(LineStatus::CANCELLED, $line->status);
        $this->assertFalse($line->wasted);
        $this->assertSame(1, $this->em->getRepository(StockMovement::class)->count(['type' => MovementType::SALE_CANCEL]) / 1);
    }

    public function testCancellingAfterCookingStartedWastesTheIngredients(): void
    {
        $rice = $this->ingredient('Riz', Dimension::MASS, '1000');
        $dish = $this->dish('Riz', '40.00', [[$rice, '250', Unit::G]]);
        $order = $this->orders->open($this->table(), $this->user());
        $line = $this->orders->addDish($order, $dish);
        $this->orders->advance($line, LineStatus::PREPARING);

        $this->orders->cancelLine($line);

        $this->assertSame('750.000', $this->refresh($rice)->stockQuantity, 'les ingrédients cuisinés ne reviennent pas');
        $this->assertTrue($line->wasted);
        $this->assertSame('0.00', $order->total(), 'un plat annulé n\'est pas facturé');
    }

    public function testServedDishCannotBeCancelledAndStatusesCannotBeSkipped(): void
    {
        $rice = $this->ingredient('Riz', Dimension::MASS, '1000');
        $dish = $this->dish('Riz', '40.00', [[$rice, '100', Unit::G]]);
        $order = $this->orders->open($this->table(), $this->user());
        $line = $this->orders->addDish($order, $dish);

        try {
            $this->orders->advance($line, LineStatus::SERVED);   // PENDING → SERVED interdit
            $this->fail('transition illégale acceptée');
        } catch (BusinessRuleViolation) {
        }

        foreach ([LineStatus::PREPARING, LineStatus::READY, LineStatus::SERVED] as $s) {
            $this->orders->advance($line, $s);
        }
        $this->expectException(BusinessRuleViolation::class);
        $this->orders->cancelLine($line);
    }

    public function testPaymentRequiresEveryDishToBeServed(): void
    {
        $rice = $this->ingredient('Riz', Dimension::MASS, '1000');
        $dish = $this->dish('Riz', '40.00', [[$rice, '100', Unit::G]]);
        $order = $this->orders->open($this->table(), $this->user());
        $line = $this->orders->addDish($order, $dish, 2);

        try {
            $this->orders->pay($order);
            $this->fail('encaissement accepté alors que des plats ne sont pas servis');
        } catch (BusinessRuleViolation) {
        }

        foreach ([LineStatus::PREPARING, LineStatus::READY, LineStatus::SERVED] as $s) {
            $this->orders->advance($line, $s);
        }
        $this->orders->pay($order);

        $this->assertSame(OrderStatus::PAID, $order->status);
        $this->assertSame('80.00', $order->total());
        $this->expectException(BusinessRuleViolation::class);
        $this->orders->addDish($order, $dish);   // commande payée : fermée
    }

    public function testOnlyOneOpenOrderPerTable(): void
    {
        $table = $this->table();
        $server = $this->user();
        $this->orders->open($table, $server);

        $this->expectException(BusinessRuleViolation::class);
        $this->orders->open($table, $server);
    }

    public function testSoldDishKeepsItsCostWhenIngredientPriceChanges(): void
    {
        $meat = $this->ingredient('Agneau', Dimension::MASS, '5000', '0.10');   // 100 MAD/kg
        $dish = $this->dish('Brochette', '70.00', [[$meat, '200', Unit::G]]);
        $order = $this->orders->open($this->table(), $this->user());

        $line = $this->orders->addDish($order, $dish);
        $this->assertSame('20.0000', $line->unitCost);

        $meat->averageCost = '0.20';   // le cours de l'agneau double
        $this->em->flush();

        $this->assertSame('20.0000', $this->refresh($line)->unitCost, 'le coût d\'un plat déjà vendu est figé');
        $this->assertSame('40.0000', $this->service(\App\Service\RecipeResolver::class)->costOfDish($dish), 'seuls les futurs plats coûtent plus');
    }

    public function testMenuIsAllOrNothingAndSplitsItsPriceAcrossDishes(): void
    {
        $a = $this->ingredient('Salade', Dimension::MASS, '5000');
        $b = $this->ingredient('Boeuf', Dimension::MASS, '5000');
        $c = $this->ingredient('Glace', Dimension::MASS, '100');
        $starter = $this->dish('Salade marocaine', '30.00', [[$a, '150', Unit::G]]);
        $main = $this->dish('Tagine boeuf', '90.00', [[$b, '300', Unit::G]]);
        $dessert = $this->dish('Glace', '30.00', [[$c, '150', Unit::G]]);   // 150 g requis, 100 g en stock !

        $menu = new Menu();
        $menu->name = 'Formule du jour';
        $menu->price = '100.00';
        foreach ([['Entrée', 1, $starter], ['Plat', 2, $main], ['Dessert', 3, $dessert]] as [$label, $pos, $dish]) {
            $course = new MenuCourse();
            $course->menu = $menu;
            $course->label = $label;
            $course->position = $pos;
            $course->dishes->add($dish);
            $menu->courses->add($course);
        }
        $this->em->persist($menu);
        $this->em->flush();
        $choices = [];
        foreach ($menu->courses as $course) {
            $choices[$course->id] = $course->dishes->first()->id;
        }
        $order = $this->orders->open($this->table(), $this->user());

        $this->assertFalse($this->service(AvailabilityService::class)->isMenuAvailable($menu));
        try {
            $this->orders->addMenu($order, $menu, $choices);
            $this->fail('menu accepté alors que le dessert est en rupture');
        } catch (InsufficientStockException) {
        }
        $this->em->clear();
        $this->assertSame('5000.000', $this->em->find(\App\Entity\Ingredient::class, $a->id)->stockQuantity, 'rien n\'est consommé');

        // Réapprovisionnement de la glace : la formule passe.
        $glace = $this->em->find(\App\Entity\Ingredient::class, $c->id);
        $glace->stockQuantity = '1000';
        $this->em->flush();
        $order = $this->em->find(\App\Entity\CustomerOrder::class, $order->id);
        $menu = $this->em->find(Menu::class, $menu->id);

        $this->orders->addMenu($order, $menu, $choices);

        $this->assertCount(3, $order->lines);
        $sum = '0';
        foreach ($order->lines as $l) {
            $sum = bcadd($sum, $l->unitPrice, 2);
        }
        $this->assertSame('100.00', $sum, 'les parts allouées somment exactement au prix de la formule');
        $this->assertSame('100.00', $order->total());
        $this->assertSame('4850.000', $this->em->find(\App\Entity\Ingredient::class, $a->id)->stockQuantity);
    }

    /** Régression : deux plats d'un même menu qui consomment le MÊME ingrédient ne doivent pas se « marcher dessus ». */
    public function testMenuDishesSharingAnIngredientAreBothDeducted(): void
    {
        $oil = $this->ingredient('Huile', Dimension::VOLUME, '1000');
        $a = $this->dish('Entrée huilée', '20.00', [[$oil, '100', Unit::ML]]);
        $b = $this->dish('Plat huilé', '40.00', [[$oil, '200', Unit::ML]]);
        $menu = new Menu();
        $menu->name = 'Duo';
        $menu->price = '50.00';
        foreach ([['Entrée', 1, $a], ['Plat', 2, $b]] as [$label, $pos, $dish]) {
            $course = new MenuCourse();
            $course->menu = $menu;
            $course->label = $label;
            $course->position = $pos;
            $course->dishes->add($dish);
            $menu->courses->add($course);
        }
        $this->em->persist($menu);
        $this->em->flush();
        $choices = [];
        foreach ($menu->courses as $c) {
            $choices[$c->id] = $c->dishes->first()->id;
        }
        $order = $this->orders->open($this->table(), $this->user());

        $this->orders->addMenu($order, $menu, $choices);

        $this->assertSame('700.000', $this->refresh($oil)->stockQuantity, '1000 - 100 - 200');
        $this->assertSame(2, $this->em->getRepository(StockMovement::class)->count(['type' => MovementType::SALE]));
    }

    public function testMenuRejectsADishThatIsNotOneOfTheCourseChoices(): void
    {
        $a = $this->ingredient('Salade', Dimension::MASS, '5000');
        $intruder = $this->dish('Intrus', '10.00', [[$a, '10', Unit::G]]);
        $starter = $this->dish('Salade', '30.00', [[$a, '150', Unit::G]]);
        $menu = new Menu();
        $menu->name = 'Mini';
        $menu->price = '30.00';
        $course = new MenuCourse();
        $course->menu = $menu;
        $course->label = 'Entrée';
        $course->dishes->add($starter);
        $menu->courses->add($course);
        $this->em->persist($menu);
        $this->em->flush();
        $order = $this->orders->open($this->table(), $this->user());

        $this->expectException(BusinessRuleViolation::class);
        $this->orders->addMenu($order, $menu, [$course->id => $intruder->id]);
    }
}
