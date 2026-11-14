<?php

namespace App\Tests\Unit;

use App\Entity\Ingredient;
use App\Enum\Allergen;
use App\Enum\AllergenStatus;
use App\Exception\BusinessRuleViolation;
use App\Exception\OpenFoodFactsException;
use App\Service\AllergenService;
use App\Service\OpenFoodFactsClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenFoodFactsTest extends TestCase
{
    private function client(MockResponse|\Closure $response): OpenFoodFactsClient
    {
        return new OpenFoodFactsClient(new MockHttpClient($response, 'https://world.openfoodfacts.org'));
    }

    private function json(array $body, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($body), ['http_code' => $status, 'response_headers' => ['content-type: application/json']]);
    }

    private function service(OpenFoodFactsClient $client): AllergenService
    {
        return new AllergenService($this->createMock(EntityManagerInterface::class), $client);
    }

    private function ingredient(?string $barcode = '3017620422003'): Ingredient
    {
        $i = new Ingredient();
        $i->name = 'Pâte à tartiner';
        $i->barcode = $barcode;

        return $i;
    }

    public function testKnownProductGivesReliableAllergens(): void
    {
        $client = $this->client($this->json(['status' => 1, 'product' => [
            'product_name' => 'Nutella',
            'allergens_tags' => ['en:milk', 'en:nuts', 'en:soybeans'],
            'traces_tags' => ['en:gluten', 'en:milk'],
            'ingredients_text' => 'Sucre, huile de palme, noisettes, lait écrémé en poudre',
            'states_tags' => ['en:complete'],
        ]]));
        $i = $this->ingredient();

        $this->service($client)->refresh($i);

        $this->assertSame(AllergenStatus::KNOWN, $i->allergenStatus);
        $this->assertEqualsCanonicalizing(['milk', 'nuts', 'soybeans'], $i->allergens);
        $this->assertSame(['gluten'], $i->traces, 'lait déjà présent : non répété dans les traces');
        $this->assertSame('Nutella', $i->offProductName);
        $this->assertSame('off', $i->allergenSource);
    }

    public function testMissingAllergenFieldIsUnknownNotAbsent(): void
    {
        $client = $this->client($this->json(['status' => 1, 'product' => ['product_name' => 'Mystère']]));
        $i = $this->ingredient();

        $this->service($client)->refresh($i);

        $this->assertSame(AllergenStatus::UNKNOWN, $i->allergenStatus);
        $this->assertSame([], $i->allergens);
    }

    public function testEmptyAllergenListWithoutIngredientsIsIncomplete(): void
    {
        $client = $this->client($this->json(['status' => 1, 'product' => ['allergens_tags' => [], 'states_tags' => []]]));
        $i = $this->ingredient();

        $this->service($client)->refresh($i);

        $this->assertSame(AllergenStatus::INCOMPLETE, $i->allergenStatus, 'une liste vide sans composition ne prouve rien');
    }

    public function testEmptyListWithIngredientsTextIsKnownToBeAllergenFree(): void
    {
        $client = $this->client($this->json(['status' => 1, 'product' => ['allergens_tags' => [], 'ingredients_text' => 'Eau, sel']]));
        $i = $this->ingredient();

        $this->service($client)->refresh($i);

        $this->assertSame(AllergenStatus::KNOWN, $i->allergenStatus);
    }

    public function testProductFlaggedToBeCompletedIsIncompleteButKeepsKnownAllergens(): void
    {
        $client = $this->client($this->json(['status' => 1, 'product' => [
            'allergens_tags' => ['en:eggs'], 'ingredients_text' => 'x', 'states_tags' => ['en:ingredients-to-be-completed'],
        ]]));
        $i = $this->ingredient();

        $this->service($client)->refresh($i);

        $this->assertSame(AllergenStatus::INCOMPLETE, $i->allergenStatus);
        $this->assertSame(['eggs'], $i->allergens, 'on conserve par prudence ce qui est connu');
    }

    public function testUnknownBarcodeKeepsPreviousDataAndRecordsError(): void
    {
        $i = $this->ingredient();
        $i->allergens = ['milk'];
        $i->allergenStatus = AllergenStatus::KNOWN;
        $i->allergenSource = 'off';

        $message = $this->service($this->client($this->json(['status' => 0], 404)))->refresh($i);

        $this->assertStringContainsString('introuvable', $message);
        $this->assertSame(['milk'], $i->allergens);
        $this->assertNotNull($i->offError);
    }

    public function testNetworkFailureIsHandledGracefully(): void
    {
        $client = $this->client(static fn () => new MockResponse('', ['error' => 'timeout']));
        $i = $this->ingredient();

        $message = $this->service($client)->refresh($i);

        $this->assertStringContainsString('Échec', $message);
        $this->assertSame(AllergenStatus::UNKNOWN, $i->allergenStatus);
        $this->assertNotNull($i->offError);
    }

    public function testServerErrorRaisesClientException(): void
    {
        $this->expectException(OpenFoodFactsException::class);
        $this->client($this->json([], 503))->fetch('3017620422003');
    }

    public function testInvalidBarcodeIsRejectedBeforeAnyRequest(): void
    {
        $called = false;
        $client = $this->client(static function () use (&$called) {
            $called = true;

            return new MockResponse('{}');
        });
        try {
            $client->fetch('abc');
            $this->fail('code-barres invalide accepté');
        } catch (OpenFoodFactsException) {
        }
        $this->assertFalse($called);
    }

    public function testManualDeclarationIsNeverOverwrittenByOpenFoodFacts(): void
    {
        $i = $this->ingredient();
        $service = $this->service($this->client($this->json(['status' => 1, 'product' => ['allergens_tags' => ['en:fish'], 'ingredients_text' => 'x']])));
        $service->declareManually($i, [Allergen::GLUTEN]);

        $service->refresh($i);

        $this->assertSame(['gluten'], $i->allergens);
        $this->assertSame('manual', $i->allergenSource);
    }

    public function testRefreshWithoutBarcodeIsRefused(): void
    {
        $this->expectException(BusinessRuleViolation::class);
        $this->service($this->client($this->json([])))->refresh($this->ingredient(null));
    }

    public function testOffTagMapping(): void
    {
        $this->assertSame(Allergen::SESAME, Allergen::fromOffTag('en:sesame-seeds'));
        $this->assertSame(Allergen::SULPHITES, Allergen::fromOffTag('en:sulphur-dioxide-and-sulphites'));
        $this->assertSame(Allergen::NUTS, Allergen::fromOffTag('en:nuts'));
        $this->assertNull(Allergen::fromOffTag('en:unicorn-dust'));
        $this->assertCount(14, Allergen::cases());
    }
}
