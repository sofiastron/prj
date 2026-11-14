<?php

namespace App\Service;

use App\Exception\OpenFoodFactsException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Client minimal de l'API Open Food Facts v2 (gratuite, sans clé) : recherche par code-barres. */
class OpenFoodFactsClient
{
    private const FIELDS = 'code,product_name,allergens_tags,traces_tags,ingredients_text,states_tags';

    public function __construct(private readonly HttpClientInterface $offClient)
    {
    }

    /**
     * @return OffProduct|null null si le code-barres est inconnu d'Open Food Facts
     *
     * @throws OpenFoodFactsException réseau, délai dépassé ou réponse illisible
     */
    public function fetch(string $barcode): ?OffProduct
    {
        $barcode = trim($barcode);
        if (!preg_match('/^\d{8,14}$/', $barcode)) {
            throw new OpenFoodFactsException('Code-barres invalide : 8 à 14 chiffres attendus.');
        }

        try {
            $response = $this->offClient->request('GET', \sprintf('/api/v2/product/%s.json', $barcode), [
                'query' => ['fields' => self::FIELDS],
            ]);
            $status = $response->getStatusCode();
            if (404 === $status) {
                return null;
            }
            if ($status >= 400) {
                throw new OpenFoodFactsException(\sprintf('Open Food Facts a répondu HTTP %d.', $status));
            }
            $data = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new OpenFoodFactsException('Open Food Facts est injoignable ou a mal répondu : '.$e->getMessage(), 0, $e);
        }

        if (1 !== ($data['status'] ?? null) || !\is_array($data['product'] ?? null)) {
            return null;
        }

        return self::parse($barcode, $data['product']);
    }

    /** @param array<string, mixed> $p */
    public static function parse(string $barcode, array $p): OffProduct
    {
        $tags = static fn (mixed $v): array => \is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
        $states = $tags($p['states_tags'] ?? []);
        $name = isset($p['product_name']) && \is_string($p['product_name']) && '' !== trim($p['product_name']) ? trim($p['product_name']) : null;
        $text = isset($p['ingredients_text']) && \is_string($p['ingredients_text']) && '' !== trim($p['ingredients_text']) ? trim($p['ingredients_text']) : null;

        return new OffProduct(
            barcode: $barcode,
            name: $name,
            allergenTags: $tags($p['allergens_tags'] ?? []),
            traceTags: $tags($p['traces_tags'] ?? []),
            hasAllergensField: \array_key_exists('allergens_tags', $p) && \is_array($p['allergens_tags']),
            ingredientsText: $text,
            markedIncomplete: \in_array('en:ingredients-to-be-completed', $states, true),
        );
    }
}
