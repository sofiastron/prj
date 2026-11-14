<?php

namespace App\Service;

use App\Enum\Allergen;

/** Allergènes d'un plat, avec indicateur de fiabilité. */
final readonly class AllergenReport
{
    /**
     * @param list<Allergen> $allergens          allergènes présents
     * @param list<Allergen> $traces             traces possibles (hors allergènes déjà présents)
     * @param list<string>   $unreliableSources  ingrédients dont l'information est inconnue ou incomplète
     */
    public function __construct(
        public array $allergens,
        public array $traces,
        public array $unreliableSources,
    ) {
    }

    /** Vrai si tous les ingrédients ont une information allergène fiable. */
    public function isComplete(): bool
    {
        return [] === $this->unreliableSources;
    }
}
