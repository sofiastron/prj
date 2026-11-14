<?php

namespace App\Enum;

/** Les 14 allergènes à déclaration obligatoire (règlement UE 1169/2011). */
enum Allergen: string
{
    case GLUTEN = 'gluten';
    case CRUSTACEANS = 'crustaceans';
    case EGGS = 'eggs';
    case FISH = 'fish';
    case PEANUTS = 'peanuts';
    case SOYBEANS = 'soybeans';
    case MILK = 'milk';
    case NUTS = 'nuts';
    case CELERY = 'celery';
    case MUSTARD = 'mustard';
    case SESAME = 'sesame';
    case SULPHITES = 'sulphites';
    case LUPIN = 'lupin';
    case MOLLUSCS = 'molluscs';

    public function label(): string
    {
        return match ($this) {
            self::GLUTEN => 'Gluten',
            self::CRUSTACEANS => 'Crustacés',
            self::EGGS => 'Œufs',
            self::FISH => 'Poisson',
            self::PEANUTS => 'Arachides',
            self::SOYBEANS => 'Soja',
            self::MILK => 'Lait',
            self::NUTS => 'Fruits à coque',
            self::CELERY => 'Céleri',
            self::MUSTARD => 'Moutarde',
            self::SESAME => 'Sésame',
            self::SULPHITES => 'Sulfites',
            self::LUPIN => 'Lupin',
            self::MOLLUSCS => 'Mollusques',
        };
    }

    /** Étiquette (« tag ») correspondante dans Open Food Facts. */
    public function offTag(): string
    {
        return match ($this) {
            self::SESAME => 'en:sesame-seeds',
            self::SULPHITES => 'en:sulphur-dioxide-and-sulphites',
            default => 'en:'.$this->value,
        };
    }

    public static function fromOffTag(string $tag): ?self
    {
        $tag = strtolower(trim($tag));
        foreach (self::cases() as $case) {
            if ($case->offTag() === $tag) {
                return $case;
            }
        }

        return match ($tag) {
            'en:sesame', 'en:sulphites', 'en:sulfites', 'en:tree-nuts', 'en:nut' => match ($tag) {
                'en:sesame' => self::SESAME,
                'en:tree-nuts', 'en:nut' => self::NUTS,
                default => self::SULPHITES,
            },
            'en:soy', 'en:soya' => self::SOYBEANS,
            'en:egg' => self::EGGS,
            default => null,
        };
    }
}
