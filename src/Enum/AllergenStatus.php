<?php

namespace App\Enum;

/**
 * Fiabilité de l'information allergène d'un ingrédient.
 * Seul KNOWN permet d'affirmer la liste affichée ; sinon la carte avertit le client.
 */
enum AllergenStatus: string
{
    case UNKNOWN = 'unknown';
    case INCOMPLETE = 'incomplete';
    case KNOWN = 'known';

    public function label(): string
    {
        return match ($this) {
            self::UNKNOWN => 'Inconnue',
            self::INCOMPLETE => 'Incomplète',
            self::KNOWN => 'Connue',
        };
    }
}
