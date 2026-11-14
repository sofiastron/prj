<?php

namespace App\Service;

/** Extrait utile d'une fiche Open Food Facts. */
final readonly class OffProduct
{
    /**
     * @param list<string> $allergenTags      ex. « en:milk »
     * @param list<string> $traceTags
     * @param bool         $hasAllergensField la fiche renseigne-t-elle seulement le champ allergènes ?
     * @param bool         $markedIncomplete  la communauté signale « ingrédients à compléter »
     */
    public function __construct(
        public string $barcode,
        public ?string $name,
        public array $allergenTags,
        public array $traceTags,
        public bool $hasAllergensField,
        public ?string $ingredientsText,
        public bool $markedIncomplete,
    ) {
    }
}
