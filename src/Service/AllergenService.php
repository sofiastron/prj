<?php

namespace App\Service;

use App\Entity\Ingredient;
use App\Enum\Allergen;
use App\Enum\AllergenStatus;
use App\Exception\BusinessRuleViolation;
use App\Exception\OpenFoodFactsException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Informations allergènes des ingrédients : Open Food Facts ou saisie manuelle du gérant.
 *
 * Principe de prudence : on n'affirme « aucun allergène » que si la source le dit clairement.
 * Information absente, incomplète ou source injoignable ⇒ statut non fiable, et la carte avertit le client.
 */
class AllergenService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OpenFoodFactsClient $client,
    ) {
    }

    /**
     * Interroge Open Food Facts pour un ingrédient muni d'un code-barres.
     * Une déclaration manuelle du gérant n'est jamais écrasée.
     *
     * @return string message de résultat pour l'utilisateur
     */
    public function refresh(Ingredient $ingredient): string
    {
        if (null === $ingredient->barcode || '' === $ingredient->barcode) {
            throw new BusinessRuleViolation(\sprintf('« %s » n\'a pas de code-barres.', $ingredient));
        }
        if ('manual' === $ingredient->allergenSource && AllergenStatus::KNOWN === $ingredient->allergenStatus) {
            return \sprintf('« %s » a une déclaration manuelle : conservée.', $ingredient);
        }

        try {
            $product = $this->client->fetch($ingredient->barcode);
        } catch (OpenFoodFactsException $e) {
            // On garde les données précédentes ; l'erreur est visible dans le back-office.
            $ingredient->offError = mb_substr($e->getMessage(), 0, 250);
            $this->em->flush();

            return \sprintf('Échec pour « %s » : %s', $ingredient, $e->getMessage());
        }

        $ingredient->offFetchedAt = new \DateTimeImmutable();
        if (null === $product) {
            $ingredient->offError = 'Code-barres introuvable sur Open Food Facts.';
            $this->em->flush();

            return \sprintf('« %s » : code-barres introuvable sur Open Food Facts.', $ingredient);
        }

        $this->apply($ingredient, $product);
        $this->em->flush();

        return \sprintf('« %s » : allergènes %s.', $ingredient, mb_strtolower($ingredient->allergenStatus->label()));
    }

    /** Interprète une fiche OFF et met à jour l'ingrédient (sans flush). */
    public function apply(Ingredient $ingredient, OffProduct $product): void
    {
        $present = $this->codes($product->allergenTags);
        $traces = array_values(array_diff($this->codes($product->traceTags), $present));

        $ingredient->allergens = $present;
        $ingredient->traces = $traces;
        $ingredient->allergenSource = 'off';
        $ingredient->offProductName = null !== $product->name ? mb_substr($product->name, 0, 250) : null;
        $ingredient->offError = null;
        $ingredient->offFetchedAt ??= new \DateTimeImmutable();

        $ingredient->allergenStatus = match (true) {
            // Le champ allergènes est absent : impossible d'affirmer quoi que ce soit.
            !$product->hasAllergensField => AllergenStatus::UNKNOWN,
            // Fiche signalée « à compléter », ou liste vide sans aucune liste d'ingrédients pour la corroborer :
            // une liste vide n'est pas une preuve d'absence d'allergène.
            $product->markedIncomplete, [] === $present && null === $product->ingredientsText => AllergenStatus::INCOMPLETE,
            default => AllergenStatus::KNOWN,
        };
    }

    /**
     * Déclaration manuelle (produits frais, sans code-barres, ou fiche OFF défaillante).
     *
     * @param list<Allergen> $allergens
     * @param list<Allergen> $traces
     */
    public function declareManually(Ingredient $ingredient, array $allergens, array $traces = []): void
    {
        $codes = array_values(array_unique(array_map(fn (Allergen $a) => $a->value, $allergens)));
        $ingredient->allergens = $codes;
        $ingredient->traces = array_values(array_diff(array_unique(array_map(fn (Allergen $a) => $a->value, $traces)), $codes));
        $ingredient->allergenStatus = AllergenStatus::KNOWN;
        $ingredient->allergenSource = 'manual';
        $ingredient->offError = null;
        $this->em->flush();
    }

    /** @param list<string> $tags @return list<string> */
    private function codes(array $tags): array
    {
        $codes = [];
        foreach ($tags as $tag) {
            if (null !== $a = Allergen::fromOffTag($tag)) {
                $codes[$a->value] = $a->value;
            }
        }

        return array_values($codes);
    }
}
