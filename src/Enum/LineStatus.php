<?php

namespace App\Enum;

/** Cycle de vie d'un plat commandé : PENDING → PREPARING → READY → SERVED (ou CANCELLED). */
enum LineStatus: string
{
    case PENDING = 'pending';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case SERVED = 'served';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::PREPARING => 'En préparation',
            self::READY => 'Prêt',
            self::SERVED => 'Servi',
            self::CANCELLED => 'Annulé',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::PENDING => self::PREPARING,
            self::PREPARING => self::READY,
            self::READY => self::SERVED,
            default => null,
        };
    }

    public function canTransitionTo(self $to): bool
    {
        if (self::CANCELLED === $to) {
            return \in_array($this, [self::PENDING, self::PREPARING, self::READY], true);
        }

        return $this->next() === $to;
    }

    /** Plat encore actif (ni annulé ni déjà servi). */
    public function isActive(): bool
    {
        return \in_array($this, [self::PENDING, self::PREPARING, self::READY], true);
    }
}
